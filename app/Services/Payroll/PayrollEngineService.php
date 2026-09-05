<?php

namespace App\Services\Payroll;

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpDisciplinary;
use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrDendaRule;
use App\Models\Pyr\PyrEmployeeSalary;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Models\Pyr\PyrPayslipDetail;
use App\Models\Pyr\PyrStatusRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

// docs/attendancepayroll_tier3.md §5.2 -- job payroll run per periode bulanan.
// Baca att_daily_status (tm2mst) via Eloquent query terpisah (CLAUDE.md §2
// pola 1 default, bukan VIEW cross-DB -- payroll run bukan hot-path).
class PayrollEngineService
{
    private const COMPONENTS = ['POKOK', 'TJ_KEAHLIAN', 'TJ_LOYALITAS', 'TJ_PRESTASI', 'BONUS_PAGI', 'BONUS_HADIR'];

    public function run(int $periodYear, int $periodMonth, string $triggeredBy): PyrPayrollRun
    {
        $run = PyrPayrollRun::firstOrCreate(
            ['period_year' => $periodYear, 'period_month' => $periodMonth],
            ['run_status' => 'DRAFT', 'triggered_by' => $triggeredBy],
        );

        if ($run->run_status === 'FINALIZED') {
            throw new \RuntimeException('Payroll run periode ini sudah FINALIZED -- buat run baru untuk revisi (Tier 3 §5.2).');
        }

        $periodStart = $run->periodStart();
        $periodEnd = $run->periodEnd();

        EmpEmployee::where('activate', true)->pluck('employee_id')
            ->each(fn (int $employeeId) => $this->calculateForEmployee($run, $employeeId, $periodStart, $periodEnd));

        $run->update(['run_status' => 'CALCULATED']);

        return $run->fresh();
    }

    public function markReviewed(PyrPayrollRun $run): void
    {
        $run->update(['run_status' => 'REVIEWED']);
    }

    public function finalize(PyrPayrollRun $run, string $finalizedBy): void
    {
        if ($run->run_status !== 'REVIEWED') {
            throw new \RuntimeException('Payroll run harus melalui REVIEWED sebelum FINALIZED (Tier 3 §5.2 step 7).');
        }

        $run->update(['run_status' => 'FINALIZED', 'finalized_by' => $finalizedBy, 'finalized_at' => now()]);
    }

    private function calculateForEmployee(PyrPayrollRun $run, int $employeeId, Carbon $periodStart, Carbon $periodEnd): void
    {
        $salary = PyrEmployeeSalary::activeFor($employeeId);
        if (! $salary) {
            // Belum punya master gaji aktif -- payslip tidak dibuat, HR harus
            // lengkapi pyr_employee_salary dulu sebelum pegawai ini bisa digaji.
            return;
        }

        $dailyStatuses = AttDailyStatus::where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('attendance_date')
            ->get();

        $activeDisciplinaries = EmpDisciplinary::where('employee_id', $employeeId)
            ->whereIn('type', ['SP1', 'SP2', 'SP3'])
            ->get();

        $totals = array_fill_keys([...self::COMPONENTS, 'UANG_ROTI', 'DENDA'], 0.0);
        $details = [];

        foreach ($dailyStatuses as $daily) {
            $spMultiplier = $this->applySpMultiplier($activeDisciplinaries, $daily);

            foreach (self::COMPONENTS as $component) {
                $amount = round($this->componentAmount($component, $daily, $salary) * $spMultiplier, 2);
                $totals[$component] += $amount;
                if ($amount != 0.0) {
                    $details[] = $this->detailRow($daily, $component, $amount);
                }
            }

            if ($daily->is_uang_roti) {
                $totals['UANG_ROTI'] += 5000;
                $details[] = $this->detailRow($daily, 'UANG_ROTI', 5000, 'Lembur > 2 jam, Staff/SPV Produksi (Tier 2 §4.4)');
            }

            $denda = $this->dendaFor($daily);
            if ($denda > 0) {
                $totals['DENDA'] += $denda;
                $details[] = $this->detailRow($daily, 'DENDA', -$denda);
            }
        }

        $totalGajiBersih = $totals['POKOK'] + $totals['TJ_KEAHLIAN'] + $totals['TJ_LOYALITAS'] + $totals['TJ_PRESTASI']
            + $totals['BONUS_PAGI'] + $totals['BONUS_HADIR'] + $totals['UANG_ROTI'] - $totals['DENDA'];

        $payslip = PyrPayslip::updateOrCreate(
            ['id_run' => $run->id_run, 'employee_id' => $employeeId],
            [
                'total_pokok' => round($totals['POKOK'], 2),
                'total_tj_keahlian' => round($totals['TJ_KEAHLIAN'], 2),
                'total_tj_loyalitas' => round($totals['TJ_LOYALITAS'], 2),
                'total_tj_prestasi' => round($totals['TJ_PRESTASI'], 2),
                'total_bonus_pagi' => round($totals['BONUS_PAGI'], 2),
                'total_bonus_hadir' => round($totals['BONUS_HADIR'], 2),
                'total_uang_roti' => round($totals['UANG_ROTI'], 2),
                'total_denda' => round($totals['DENDA'], 2),
                'total_gaji_bersih' => round($totalGajiBersih, 2),
            ],
        );

        $payslip->details()->delete();
        foreach ($details as $detail) {
            $detail['id_payslip'] = $payslip->id_payslip;
            PyrPayslipDetail::create($detail);
        }
    }

    private function componentAmount(string $component, AttDailyStatus $daily, PyrEmployeeSalary $salary): float
    {
        $effect = PyrStatusRule::effectFor($daily->status_code, $component, $daily->attendance_date);

        $dailyRate = match ($component) {
            'POKOK' => $salary->dailyBasicSalary(),
            'TJ_KEAHLIAN' => $salary->dailyTjKeahlian(),
            'TJ_LOYALITAS' => round(((float) $salary->tj_loyalitas_monthly) / 30, 2),
            'TJ_PRESTASI' => round(((float) $salary->tj_prestasi_monthly) / 30, 2),
            'BONUS_PAGI' => (float) $salary->bonus_pagi_amount,
            'BONUS_HADIR' => (float) $salary->bonus_hadir_amount,
            default => 0.0,
        };

        return match ($effect) {
            'ZERO' => 0.0,
            'PRORATA' => $this->prorata($dailyRate, $daily),
            default => $dailyRate,
        };
    }

    // Formula prorata Tier 2 §3 -- menit_hilang menggabungkan telat + pulang
    // awal + KJK ("PAT-5/PAT+5 akumulasi keduanya"), dibagi durasi_shift_menit.
    private function prorata(float $dailyRate, AttDailyStatus $daily): float
    {
        if (! $daily->shift_start || ! $daily->shift_end) {
            return $dailyRate;
        }

        $start = Carbon::parse($daily->shift_start);
        $end = Carbon::parse($daily->shift_end);
        $shiftMinutes = $end->lt($start) ? $start->diffInMinutes($end->addDay()) : $start->diffInMinutes($end);

        if ($shiftMinutes <= 0) {
            return $dailyRate;
        }

        $minutesHilang = $daily->minutes_late + $daily->minutes_early_leave + $daily->minutes_out_during_shift;
        $potongan = ($minutesHilang / $shiftMinutes) * $dailyRate;

        return max(0.0, round($dailyRate - $potongan, 2));
    }

    private function dendaFor(AttDailyStatus $daily): float
    {
        $rule = PyrDendaRule::forStatus($daily->status_code, $daily->attendance_date);
        if (! $rule) {
            return 0.0;
        }

        if ($daily->status_code === 'T+5' && str_contains($rule->condition_note ?? '', 'ke-4')) {
            return ($daily->late_violation_seq_in_period ?? 0) > 3 ? (float) $rule->denda_amount : 0.0;
        }

        return (float) $rule->denda_amount;
    }

    // SP1/SP2: potongan 50% seluruh komponen selama N hari KERJA berturut sejak
    // effective_start (Tier 2 §6) -- working_days_counted diupdate di sini,
    // persisten lintas payroll run kalau masa SP menyeberang bulan.
    // SP3: zero-total tanpa counter (keputusan administratif, di luar formula rutin).
    private function applySpMultiplier(Collection $activeDisciplinaries, AttDailyStatus $daily): float
    {
        $covering = $activeDisciplinaries->filter(fn (EmpDisciplinary $d) => $d->coversDate($daily->attendance_date));
        if ($covering->isEmpty()) {
            return 1.0;
        }

        if ($covering->contains(fn (EmpDisciplinary $d) => $d->type === 'SP3')) {
            return 0.0;
        }

        $sp = $covering->firstWhere('type', 'SP2') ?? $covering->firstWhere('type', 'SP1');
        if (! $sp || ! $sp->working_days_target || $sp->working_days_counted >= $sp->working_days_target) {
            return 1.0;
        }

        $isWorkingDay = $daily->shift_start !== null;
        if (! $isWorkingDay) {
            return 1.0;
        }

        $sp->increment('working_days_counted');

        return 0.5;
    }

    private function detailRow(AttDailyStatus $daily, string $componentCode, float $amount, ?string $note = null): array
    {
        return [
            'attendance_date' => $daily->attendance_date,
            'status_code' => $daily->status_code,
            'component_code' => $componentCode,
            'amount' => $amount,
            'note' => $note,
        ];
    }
}
