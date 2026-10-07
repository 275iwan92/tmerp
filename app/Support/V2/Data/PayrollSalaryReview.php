<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use Devlib\Locale;
use Illuminate\Support\Collection;

// HRM > Payroll > Salary Review (Excel): rincian gaji per pegawai satu periode payroll, diunduh .xlsx supaya direksi bisa menelaahnya manual di laptop.
// Nilai gaji dibaca lewat model (kolom terenkripsi didekripsi PayrollEncrypted). Hak akses = SecUser::canViewPayrollAmounts (admin / DIREKSI / MGR_ACC).
// Berbeda dari Bank Transfer: periode yang sudah dihitung (CALCULATED ke atas) ikut, supaya bisa ditelaah sebelum dikonfirmasi; slip beranomali tetap tampil (ditandai).
final class PayrollSalaryReview
{
    public const RUN_STATUSES = ['CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED'];

    private const AMOUNTS = [
        'total_pokok' => 'Basic Salary', 'total_tj_keahlian' => 'Skill Allowance', 'total_tj_loyalitas' => 'Loyalty Allowance',
        'total_tj_prestasi' => 'Performance Allowance', 'total_bonus_pagi' => 'Morning Bonus', 'total_bonus_hadir' => 'Attendance Bonus',
        'total_uang_roti' => 'Meal Allowance', 'total_overtime_pay' => 'Overtime Pay', 'total_denda' => 'Penalty',
        'total_bpjs_employee' => 'BPJS (Employee)', 'total_pph21' => 'Income Tax (PPh 21)', 'total_gaji_bersih' => 'Net Salary',
    ];

    /** @return Collection<int, PyrPayrollRun> */
    public static function runs(): Collection
    {
        return PyrPayrollRun::whereIn('run_status', self::RUN_STATUSES)->orderByDesc('period_start')->get();
    }

    public static function run(?int $id): ?PyrPayrollRun
    {
        return $id ? PyrPayrollRun::whereIn('run_status', self::RUN_STATUSES)->find($id) : null;
    }

    public static function defaultRun(): ?int
    {
        return PyrPayrollRun::whereIn('run_status', self::RUN_STATUSES)->orderByDesc('period_start')->value('id_run');
    }

    /** @return array<int, string> id periode => "dd-Mmm-yyyy — dd-Mmm-yyyy" */
    public static function runOptions(): array
    {
        return self::runs()->mapWithKeys(fn (PyrPayrollRun $r) => [$r->id_run => Locale::d($r->period_start).' — '.Locale::d($r->period_end)])->all();
    }

    /** @return list<string> */
    public static function headings(): array
    {
        return [t('Employee No.'), t('Name'), t('Department'), t('Position'), ...array_map(fn ($l) => t($l), array_values(self::AMOUNTS)), t('Anomaly')];
    }

    /** Baris data + baris TOTAL di akhir. @return list<list<string|float>> */
    public static function rows(int $runId): array
    {
        abort_unless(self::run($runId), 404);

        $payslips = PyrPayslip::where('id_run', $runId)->get();
        $employees = EmpEmployee::with('position.department')->whereIn('employee_id', $payslips->pluck('employee_id'))->get()->keyBy('employee_id');

        $rows = $payslips->map(function (PyrPayslip $p) use ($employees) {
            $e = $employees->get($p->employee_id);

            return [
                (string) $e?->employee_code, (string) $e?->full_name, (string) ($e?->position?->department?->department_name ?? ''), (string) ($e?->position?->position_name ?? ''),
                ...array_map(fn (string $col) => (float) $p->{$col}, array_keys(self::AMOUNTS)),
                $p->has_anomaly ? ($p->anomaly_note ?: t('Yes')) : '',
            ];
        })->sortBy(fn (array $r) => $r[1])->values()->all();

        $totals = ['', t('TOTAL'), '', ''];
        foreach (array_keys(self::AMOUNTS) as $i => $_) {
            $totals[] = array_sum(array_column($rows, 4 + $i));
        }
        $totals[] = '';

        return [...$rows, $totals];
    }

    public static function filename(PyrPayrollRun $run): string
    {
        return 'salary-review-'.$run->period_start->toDateString().'.xlsx';
    }
}
