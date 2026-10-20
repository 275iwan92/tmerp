<?php

/*
 * REMARK
 * Tujuan       : Master gaji dan pajak pegawai (pyr_employee_salary) dari layar v2 hrm-salary-form: gaji pokok, tunjangan, bonus harian, status PTKP, saklar BPJS dan PPh21 per pegawai; setiap perubahan = versi baru (versi lama dinonaktifkan dengan effective_to), tidak menimpa.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::hrm.payroll-employee-salary (diarsipkan di tmerp_unused/payroll_employee_salary_v1_2026-10-20.blade.php): angka tidak negatif, gaji pokok wajib, PTKP wajib bila PPh21 aktif, versi lama activate = 0 + effective_to hari ini lalu baris baru effective_from hari ini, hanya yang berhak payroll atas pegawai itu (PayrollAccess). Baru (Jo 2026-10-20, mode full speed): PTKP harus dari daftar, batas atas angka, penyimpanan satu transaksi di tm2pyr, tanpa perubahan tidak membuat versi baru, riwayat versi ditampilkan.
 * Input        : pegawai, array isian, user yang bertindak.
 * Output       : tm2pyr.pyr_employee_salary bertambah satu versi (lama dinonaktifkan); ValidationException atau AuthorizationException bila aturan dilanggar. Gaji pokok terenkripsi lewat cast model (PayrollEncrypted).
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.salary.save), layar hrm-salary-form, tes EmployeeSalarySettingServiceTest; dibaca PayrollEngineService.
 * Status       : Aktif (T8 Payroll, 2026-10-20).
 */

namespace App\Services\Payroll;

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrEmployeeSalary;
use App\Models\Sys\SecUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EmployeeSalarySettingService
{
    public const PTKP = [
        'TK/0' => 'TK/0 (Single)', 'TK/1' => 'TK/1 (1 Dependent)', 'TK/2' => 'TK/2 (2 Dependents)', 'TK/3' => 'TK/3 (3 Dependents)',
        'K/0' => 'K/0 (Married, 0 Children)', 'K/1' => 'K/1 (Married, 1 Child)', 'K/2' => 'K/2 (Married, 2 Children)', 'K/3' => 'K/3 (Married, 3 Children)',
    ];

    private const AMOUNTS = ['basic_salary_monthly', 'tj_keahlian_monthly', 'tj_loyalitas_monthly', 'tj_prestasi_monthly', 'bonus_pagi_amount', 'bonus_hadir_amount'];

    public static function can(?SecUser $user, EmpEmployee $e): bool
    {
        return PayrollAccess::canProcess($user) && PayrollAccess::canSeeEmployee($user, (int) $e->employee_id);
    }

    public function save(EmpEmployee $e, array $in, SecUser $user): ?PyrEmployeeSalary
    {
        if (! self::can($user, $e)) {
            throw new AuthorizationException('You do not have access to view or set the salary of this employee.');
        }
        $money = ['required', 'numeric', 'min:0', 'max:99999999999'];
        $opt = ['nullable', 'numeric', 'min:0', 'max:99999999999'];
        $d = Validator::make($in, [
            'basic_salary_monthly' => $money,
            'tj_keahlian_monthly' => $opt,
            'tj_loyalitas_monthly' => $opt,
            'tj_prestasi_monthly' => $opt,
            'bonus_pagi_amount' => $opt,
            'bonus_hadir_amount' => $opt,
            'ptkp_status' => ['nullable', Rule::in(array_keys(self::PTKP))],
            'bpjs_active' => ['boolean'],
            'pph21_active' => ['boolean'],
        ], ['ptkp_status.in' => 'Choose a PTKP status from the list.'], ['basic_salary_monthly' => 'Base salary', 'tj_keahlian_monthly' => 'Skill allowance', 'tj_loyalitas_monthly' => 'Loyalty allowance',
            'tj_prestasi_monthly' => 'Performance allowance', 'bonus_pagi_amount' => 'Morning bonus', 'bonus_hadir_amount' => 'Attendance bonus', 'ptkp_status' => 'PTKP status'])->validate();
        if ($d['pph21_active'] && ! ($d['ptkp_status'] ?? null)) {
            throw ValidationException::withMessages(['ptkp_status' => 'PTKP status is required when PPh21 is enabled.']);
        }
        $new = ['ptkp_status' => ($d['ptkp_status'] ?? null) ?: null, 'bpjs_active' => (bool) $d['bpjs_active'], 'pph21_active' => (bool) $d['pph21_active']];
        foreach (self::AMOUNTS as $k) {
            $new[$k] = (float) ($d[$k] ?? 0);
        }

        return DB::connection('tm2pyr')->transaction(function () use ($e, $new, $user) {
            $cur = PyrEmployeeSalary::where('employee_id', $e->employee_id)->where('activate', true)->lockForUpdate()->latest('effective_from')->first();
            if ($cur && $this->same($cur, $new)) {
                return null;
            }
            PyrEmployeeSalary::where('employee_id', $e->employee_id)->where('activate', true)->update(['activate' => false, 'effective_to' => now()->toDateString()]);

            return PyrEmployeeSalary::create($new + ['employee_id' => $e->employee_id, 'effective_from' => now()->toDateString(), 'activate' => true, 'created_by' => $user->login]);
        });
    }

    private function same(PyrEmployeeSalary $cur, array $new): bool
    {
        foreach (self::AMOUNTS as $k) {
            if (abs((float) $cur->{$k} - $new[$k]) > 0.004) {
                return false;
            }
        }

        return ($cur->ptkp_status ?: null) === $new['ptkp_status'] && (bool) $cur->bpjs_active === $new['bpjs_active'] && (bool) $cur->pph21_active === $new['pph21_active'];
    }

    /** Riwayat versi gaji pegawai ini, terbaru di atas (hanya untuk yang berhak). */
    public static function versions(EmpEmployee $e, ?SecUser $user): array
    {
        if (! self::can($user, $e)) {
            return [];
        }

        return PyrEmployeeSalary::where('employee_id', $e->employee_id)->orderByDesc('effective_from')->orderByDesc('id_salary')->get()
            ->map(fn (PyrEmployeeSalary $s) => ['from' => $s->effective_from?->format('d-M-Y') ?? '—', 'to' => $s->effective_to?->format('d-M-Y') ?? '—', 'basic' => number_format((float) $s->basic_salary_monthly, 0),
                'skill' => number_format((float) $s->tj_keahlian_monthly, 0), 'ptkp' => (string) ($s->ptkp_status ?? '—'), 'bpjs' => $s->bpjs_active ? 'Yes' : 'No', 'pph21' => $s->pph21_active ? 'Yes' : 'No',
                'by' => (string) ($s->created_by ?? '')])->all();
    }

    public function history(EmpEmployee $e, ?SecUser $user): array
    {
        return array_map(fn ($v) => ['at' => $v['from'], 'by' => $v['by'], 'action' => $v['to'] === '—' ? 'Current' : 'Replaced', 'note' => trim('PTKP '.$v['ptkp'].' · BPJS '.$v['bpjs'].' · PPh21 '.$v['pph21'])], self::versions($e, $user));
    }
}
