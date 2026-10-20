<?php

/*
 * REMARK
 * Tujuan       : Slip gaji (pyr_payslip) untuk layar v2: pilihan kanal per slip (cetak kertas dan WhatsApp), stempel waktu cetak, dan data satu lembar slip yang dipakai SEMUA jalur cetak (satu slip, seluruh periode) supaya isinya tidak pernah berbeda. Slip dibuat oleh PayrollEngineService saat periode dihitung, bukan dari sini.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::hrm.payroll-payslip-list, payroll-payslip-print dan payroll-payslip-print-all (diarsipkan di tmerp_unused/payroll_payslip_*_v1_2026-10-20.blade.php): print_flag / wa_flag per slip, printed_at diisi saat slip dibuka untuk dicetak, cetak seluruh periode hanya slip dengan print_flag dan tanpa anomali, WhatsApp hanya bila pegawai punya nomor, cakupan data lewat PayrollAccess. Baru (Jo 2026-10-20, mode full speed): kanal hanya bisa diubah untuk slip tanpa anomali (v1 hanya menyembunyikan tombolnya), semua perubahan dicek cakupan di server.
 * Input        : slip (pyr_payslip), kanal print atau wa, user yang bertindak.
 * Output       : pyr_payslip.print_flag / wa_flag / printed_at berubah; sheet() mengembalikan data lembar slip; ValidationException atau AuthorizationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.payslip.channel), layar hrm-payslip-form, App\Support\V2\Print\PayslipPrint, halaman cetak seluruh periode, tes PayslipServiceTest.
 * Status       : Aktif (T8 Payroll, 2026-10-20).
 */

namespace App\Services\Payroll;

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Models\Sys\SecUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PayslipService
{
    /** Status periode yang sudah punya slip sah untuk dilihat. */
    public const RUN_STATUSES = ['CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED'];

    /** Kode status presensi yang dihitung sebagai telat pada ringkasan kehadiran slip (sama dengan v1). */
    private const LATE = ['T-5', 'T+5', 'T+15', 'PAT-5', 'PAT+5'];

    /** Slip hanya boleh dilihat user yang punya cakupan payroll atas pegawainya. */
    public static function can(?SecUser $user, PyrPayslip $p): bool
    {
        return PayrollAccess::canViewPayslips($user) && PayrollAccess::canSeeEmployee($user, (int) $p->employee_id);
    }

    /** Ubah kanal slip: 'print' (kertas) atau 'wa' (WhatsApp, butuh nomor). Slip anomali belum sah, kanalnya terkunci. */
    public function toggleChannel(PyrPayslip $p, string $channel, SecUser $user): PyrPayslip
    {
        if (! self::can($user, $p)) {
            throw new AuthorizationException('You do not have access to this payslip.');
        }
        if (! in_array($channel, ['print', 'wa'], true)) {
            throw ValidationException::withMessages(['document' => 'Unknown channel.']);
        }
        if ($p->has_anomaly) {
            throw ValidationException::withMessages(['document' => 'An anomaly payslip is not valid yet; fix it in Master Employee Salary & Tax first.']);
        }
        if ($channel === 'wa' && blank(EmpEmployee::where('employee_id', $p->employee_id)->value('phone'))) {
            throw ValidationException::withMessages(['document' => 'This employee has no WhatsApp number yet — complete it first in Master Employee.']);
        }
        $col = $channel === 'print' ? 'print_flag' : 'wa_flag';
        $p->forceFill([$col => ! $p->{$col}])->save();

        return $p;
    }

    /** Membuka slip untuk dicetak = niat mencetaknya: stempel pertama dipakai grid untuk menghitung sisa "belum dicetak". */
    public function stampPrinted(PyrPayslip $p): void
    {
        if (! $p->printed_at) {
            $p->forceFill(['printed_at' => now()])->save();
        }
    }

    /** Slip periode ini yang ikut cetak massal: print_flag menyala, bukan anomali, dalam cakupan user. */
    public static function printable(PyrPayrollRun $run, ?SecUser $user): Collection
    {
        return PayrollAccess::restrict(PyrPayslip::where('id_run', $run->id_run)->where('print_flag', true)->where('has_anomaly', false), $user)->orderBy('employee_id')->get();
    }

    /** Cetak massal selesai: stempel printed_at semua slip yang ikut tumpukan (browser tidak memberi tahu printer selesai, jadi ini tombol manual). */
    public function markRunPrinted(PyrPayrollRun $run, SecUser $user): int
    {
        if (! PayrollAccess::canViewPayslips($user)) {
            throw new AuthorizationException('You do not have access to payslips.');
        }
        $ids = self::printable($run, $user)->pluck('id_payslip');

        return PyrPayslip::whereIn('id_payslip', $ids)->update(['printed_at' => now()]);
    }

    /**
     * Data satu lembar slip: pegawai, cara bayar, kehadiran, baris pendapatan dan potongan, gaji bersih.
     *
     * @return array{employee: ?EmpEmployee, bank: ?EmpBankAccount, attendance: array<string, int>, earnings: array<string, float>, deductions: array<string, float>, net: float}
     */
    public static function sheet(PyrPayslip $p): array
    {
        $run = $p->run ?? PyrPayrollRun::findOrFail($p->id_run);
        $att = AttDailyStatus::where('employee_id', $p->employee_id)->whereBetween('attendance_date', [$run->period_start->toDateString(), $run->period_end->toDateString()])->get();

        return [
            'employee' => EmpEmployee::with('position.department')->find($p->employee_id),
            'bank' => EmpBankAccount::where('employee_id', $p->employee_id)->where('is_primary', true)->where('activate', true)->first(),
            'attendance' => ['present' => $att->where('status_code', 'H')->count(), 'late' => $att->whereIn('status_code', self::LATE)->count(), 'absent' => $att->where('status_code', 'A')->count()],
            'earnings' => ['Base salary' => (float) $p->total_pokok, 'Skill allowance' => (float) $p->total_tj_keahlian, 'Loyalty allowance' => (float) $p->total_tj_loyalitas,
                'Performance allowance' => (float) $p->total_tj_prestasi, 'Morning + attendance bonus' => (float) $p->total_bonus_pagi + (float) $p->total_bonus_hadir,
                'Overtime pay' => (float) $p->total_overtime_pay, 'Meal allowance' => (float) $p->total_uang_roti],
            'deductions' => ['Attendance fines' => (float) $p->total_denda, 'BPJS (employment + health)' => (float) $p->total_bpjs_employee, 'PPh 21' => (float) $p->total_pph21],
            'net' => (float) $p->total_gaji_bersih,
        ];
    }
}
