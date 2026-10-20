<?php

/*
 * REMARK
 * Tujuan       : Siklus periode payroll dari layar v2 hrm-payroll-run-form: Run Calculation (periode baru atau hitung ulang DRAFT / CALCULATED), Verify, Confirm, Mark as Paid, Mark Payslips as Sent, Close Period; ringkasan Bank vs Cash dan jumlah anomali. Perhitungan dan urutan status tetap di PayrollEngineService (dipakai apa adanya); layanan ini menjaga akses, validasi periode dan mengubah kegagalan engine menjadi pesan form.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::hrm.payroll-batch-run (diarsipkan di tmerp_unused/payroll_batch_run_v1_2026-10-20.blade.php): siklus DRAFT, CALCULATED, VERIFIED, CONFIRMED, PAID, CLOSED; ringkasan = payslip dalam cakupan user, Bank = rekening primer aktif, Cash = selainnya, anomali dikeluarkan dari total. Baru (Jo 2026-10-20, mode full speed): hanya user berhak payroll, periode divalidasi (tipe, tanggal, akhir >= awal, selang wajar), Mark Payslips as Sent hanya setelah PAID dan sekali, RuntimeException engine menjadi ValidationException (v1 hanya menangkapnya di Confirm).
 * Input        : id periode atau isian periode baru, metode verifikasi, user yang bertindak.
 * Output       : tm2pyr.pyr_payroll_run dan pyr_payslip berubah lewat engine; ValidationException (kunci document / field) atau AuthorizationException.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.payrollrun.save), layar hrm-payroll-run-form, tes PayrollRunServiceTest.
 * Status       : Aktif (T8 Payroll, 2026-10-20).
 */

namespace App\Services\Payroll;

use App\Models\Master\EmpBankAccount;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Models\Sys\SecUser;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PayrollRunService
{
    public const TYPES = ['MONTHLY' => 'Monthly', 'WEEKLY' => 'Weekly'];

    public const STATUSES = ['DRAFT', 'CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED'];

    public const METHODS = ['FULL' => 'Check all employees (FULL)', 'RANDOM_SAMPLE' => 'Check random sample (RANDOM_SAMPLE)'];

    private function engine(): PayrollEngineService
    {
        return app(PayrollEngineService::class);
    }

    private function guard(SecUser $user): void
    {
        if (! PayrollAccess::canProcess($user)) {
            throw new AuthorizationException('You do not have access to payroll.');
        }
    }

    /** Jalankan perhitungan periode (baru, atau ulang selama DRAFT / CALCULATED). */
    public function calculate(array $in, SecUser $user): PyrPayrollRun
    {
        $this->guard($user);
        $d = Validator::make($in, [
            'period_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
        ], ['period_end.after_or_equal' => 'The period end cannot be before the start.'], ['period_type' => 'Period', 'period_start' => 'From', 'period_end' => 'Until'])->validate();
        $start = Carbon::parse($d['period_start']);
        $end = Carbon::parse($d['period_end']);
        if ($start->diffInDays($end) > 40) {
            throw ValidationException::withMessages(['period_end' => 'A payroll period cannot be longer than 40 days.']);
        }

        return $this->run(fn () => $this->engine()->calculate($d['period_type'], $start, $end, $user->login));
    }

    public function verify(PyrPayrollRun $r, string $method, SecUser $user): void
    {
        $this->guard($user);
        if (! array_key_exists($method, self::METHODS)) {
            throw ValidationException::withMessages(['method' => 'Choose a verification method.']);
        }
        $this->run(fn () => $this->engine()->verify($this->fresh($r), $method, null, $user->login));
    }

    public function confirm(PyrPayrollRun $r, SecUser $user): void
    {
        $this->guard($user);
        $this->run(fn () => $this->engine()->confirm($this->fresh($r), $user->login));
    }

    public function paid(PyrPayrollRun $r, SecUser $user): void
    {
        $this->guard($user);
        $this->run(fn () => $this->engine()->markPaid($this->fresh($r), $user->login));
    }

    public function sent(PyrPayrollRun $r, SecUser $user): void
    {
        $this->guard($user);
        $fresh = $this->fresh($r);
        if ($fresh->run_status !== 'PAID' || $fresh->payslip_sent_at) {
            throw ValidationException::withMessages(['document' => 'Payslips can be marked as sent once, after the run is PAID.']);
        }
        $this->engine()->markPayslipsSent($fresh);
    }

    public function close(PyrPayrollRun $r, SecUser $user): PyrPayrollRun
    {
        $this->guard($user);

        return $this->run(fn () => $this->engine()->close($this->fresh($r), $user->login));
    }

    private function fresh(PyrPayrollRun $r): PyrPayrollRun
    {
        return PyrPayrollRun::findOrFail($r->getKey());
    }

    private function run(callable $f): mixed
    {
        try {
            return $f();
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['document' => $e->getMessage()]);
        }
    }

    /** Ringkasan Bank vs Cash dan anomali, hanya payslip dalam cakupan user (nominal tidak bisa di-SUM di SQL: terenkripsi). */
    public static function summary(PyrPayrollRun $r, ?SecUser $user): ?array
    {
        if (! in_array($r->run_status, ['CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED'], true)) {
            return null;
        }
        $slips = PayrollAccess::restrict(PyrPayslip::where('id_run', $r->id_run), $user)->get();
        $bankIds = EmpBankAccount::whereIn('employee_id', $slips->pluck('employee_id'))->where('is_primary', true)->where('activate', true)->pluck('employee_id')->all();
        $ok = $slips->where('has_anomaly', false);
        $bank = $ok->filter(fn (PyrPayslip $p) => in_array($p->employee_id, $bankIds, true));
        $cash = $ok->reject(fn (PyrPayslip $p) => in_array($p->employee_id, $bankIds, true));

        return ['employees' => $slips->count(), 'bank_count' => $bank->count(), 'bank_total' => $bank->sum(fn ($p) => (float) $p->total_gaji_bersih), 'cash_count' => $cash->count(),
            'cash_total' => $cash->sum(fn ($p) => (float) $p->total_gaji_bersih), 'grand_total' => $ok->sum(fn ($p) => (float) $p->total_gaji_bersih), 'anomalies' => $slips->where('has_anomaly', true)->count()];
    }

    /** Riwayat tab History: tiap langkah siklus, terbaru di atas. */
    public function history(PyrPayrollRun $r): array
    {
        // Urutan siklus tetap (bukan urutan waktu): beberapa langkah bisa tercatat pada detik yang sama.
        $steps = [['Calculated', 'triggered_by', 'triggered_at'], ['Verified', 'verified_by', 'verified_at'], ['Confirmed', 'confirmed_by', 'confirmed_at'], ['Paid', 'paid_by', 'paid_at'],
            ['Payslips sent', null, 'payslip_sent_at'], ['Closed', 'closed_by', 'closed_at']];
        $rows = [];
        foreach ($steps as [$label, $by, $at]) {
            if ($r->{$at}) {
                $rows[] = ['at' => $r->{$at}->toDateTimeString(), 'by' => $by ? (string) $r->{$by} : '', 'action' => $label, 'note' => $label === 'Verified' ? (string) $r->verification_method : ''];
            }
        }
        $rows = array_reverse($rows);

        return $rows;
    }
}
