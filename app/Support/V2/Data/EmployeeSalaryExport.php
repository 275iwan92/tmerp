<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrEmployeeSalary;
use Devlib\Locale;

// HRM > Payroll > Master Employee Salary & Tax: unduhan Excel setelan gaji master per pegawai aktif (BUKAN slip payroll) untuk telaah manual direksi.
// Nilai gaji pokok terenkripsi (PayrollEncrypted) dibaca lewat model. Hak akses = SecUser::canExportPayrollAmounts (hanya GM / Direksi, tanpa bypass admin).
final class EmployeeSalaryExport
{
    /** @return list<string> */
    public static function headings(): array
    {
        return array_map(fn (string $l) => t($l), ['Employee No.', 'Name', 'Department', 'Position', 'Base Salary/Month', 'Skill Allowance/Month', 'Loyalty Allowance/Month',
            'Performance Allowance/Month', 'Morning Bonus', 'Attendance Bonus', 'PTKP', 'BPJS', 'PPh21', 'Effective From', 'Salary Setup']);
    }

    /** Satu baris per pegawai aktif (pegawai tanpa setelan gaji = sel kosong, "Not set"). @return list<list<string|float>> */
    public static function rows(): array
    {
        $employees = EmpEmployee::with('position.department')->where('activate', true)->orderBy('full_name')->get();
        $salaries = PyrEmployeeSalary::whereIn('employee_id', $employees->modelKeys())->where('activate', true)->get()->keyBy('employee_id');

        return $employees->map(function (EmpEmployee $e) use ($salaries) {
            $s = $salaries->get($e->getKey());
            $money = fn (string $col) => $s ? (float) $s->{$col} : '';

            return [
                (string) $e->employee_code, (string) $e->full_name, (string) ($e->position?->department?->department_name ?? ''), (string) ($e->position?->position_name ?? ''),
                $money('basic_salary_monthly'), $money('tj_keahlian_monthly'), $money('tj_loyalitas_monthly'), $money('tj_prestasi_monthly'),
                $money('bonus_pagi_amount'), $money('bonus_hadir_amount'),
                (string) ($s?->ptkp_status ?? ''), $s ? t($s->bpjs_active ? 'Active' : 'No') : '', $s ? t($s->pph21_active ? 'Active' : 'No') : '',
                $s?->effective_from ? Locale::d($s->effective_from) : '', t($s ? 'Set' : 'Not set'),
            ];
        })->values()->all();
    }

    public static function filename(): string
    {
        return 'master-employee-salary-'.now()->format('Ymd').'.xlsx';
    }
}
