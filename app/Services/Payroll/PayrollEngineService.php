<?php

namespace App\Services\Payroll;

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpDisciplinary;
use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrDendaRule;
use App\Models\Pyr\PyrEmployeeSalary;
use App\Models\Pyr\PyrBpjsRate;
use App\Models\Pyr\PyrOvertimeRate;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Models\Pyr\PyrPayslipDetail;
use App\Models\Pyr\PyrPph21TerBracket;
use App\Models\Pyr\PyrStatusRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

// Alur bisnis lengkap (ditulis user 2026-09-05, menggantikan asumsi awal
// Tier3 §5.2 yang cuma DRAFT->CALCULATED->REVIEWED->FINALIZED bulanan):
// DRAFT (periode dibuat kosong) -> CALCULATED (estimasi H-1, boleh
// dijalankan ulang mis. proses ulang H) -> VERIFIED (HR cek jam masuk/
// keluar semua pegawai atau sample random) -> CONFIRMED (presensi dikunci,
// lanjut ke pembayaran) -> PAID (transfer bank/cash dieksekusi) -> CLOSED
// (slip terkirim, masuk jurnal, next_run_id dibuat otomatis kosong).
// Baca att_daily_status (tm2mst) via Eloquent query terpisah (CLAUDE.md §2
// pola 1 default, bukan VIEW cross-DB -- payroll run bukan hot-path).
//
// HIGH IMPROVEMENT 2026-09-07 (dipicu mempelajari stored procedure sistem
// lama docs/ddl/tmhrm.sql): rule matrix (status/denda/overtime) di-preload
// SEKALI per run ke memori (bukan query per baris presensi -- dulu bisa
// ribuan query kecil utk 137 pegawai x banyak hari x 6 komponen), tambah
// pembayaran lembur per jam data-driven (pyr_overtime_rate, sebelumnya
// cuma Uang Roti flat), dan payslip anomali (has_anomaly/anomaly_note)
// supaya pegawai tanpa pyr_employee_salary aktif TETAP KELIHATAN di run
// (dulu senyap diskip total), plus guard confirm() menolak kalau masih
// ada anomali belum diselesaikan.
class PayrollEngineService
{
    private const COMPONENTS = ['POKOK', 'TJ_KEAHLIAN', 'TJ_LOYALITAS', 'TJ_PRESTASI', 'BONUS_PAGI', 'BONUS_HADIR'];

    public function __construct(
        private readonly Pph21TerService $pph21Service = new Pph21TerService(),
        private readonly BpjsCalculatorService $bpjsService = new BpjsCalculatorService(),
    ) {}

    // Dipanggil H-1 (estimasi) maupun H (proses ulang) -- idempotent,
    // payslip di-updateOrCreate tiap kali dipanggil selama status masih
    // DRAFT/CALCULATED (belum VERIFIED).
    public function calculate(string $periodType, Carbon $periodStart, Carbon $periodEnd, string $triggeredBy): PyrPayrollRun
    {
        $run = PyrPayrollRun::firstOrCreate(
            ['period_type' => $periodType, 'period_start' => $periodStart->toDateString()],
            ['period_end' => $periodEnd->toDateString(), 'run_status' => 'DRAFT', 'triggered_by' => $triggeredBy, 'triggered_at' => now()],
        );

        if (! in_array($run->run_status, ['DRAFT', 'CALCULATED'], true)) {
            throw new \RuntimeException(str_replace(':status', $run->run_status, t('The payroll run is already :status -- attendance is locked and cannot be recalculated.')));
        }

        // Preload sekali per run -- rule matrix kecil (belasan status x 6
        // komponen + denda + overtime), jauh lebih murah dari query per
        // baris presensi x per komponen.
        $statusRules = PyrStatusRule::all();
        $dendaRules = PyrDendaRule::all();
        $overtimeRates = PyrOvertimeRate::all();
        $pph21Brackets = PyrPph21TerBracket::all();
        $bpjsRates = PyrBpjsRate::all();

        EmpEmployee::where('activate', true)
            ->where('payment_frequency', $periodType)
            ->pluck('employee_id')
            ->each(fn (int $employeeId) => $this->calculateForEmployee(
                $run, $employeeId, $periodStart, $periodEnd, $statusRules, $dendaRules, $overtimeRates, $pph21Brackets, $bpjsRates,
            ));

        $run->update(['run_status' => 'CALCULATED']);

        return $run->fresh();
    }

    // HR cek jam masuk/keluar tiap pegawai (FULL) atau sample acak
    // (RANDOM_SAMPLE) sebelum presensi dikunci.
    public function verify(PyrPayrollRun $run, string $method, ?array $sampleEmployeeIds, string $verifiedBy): void
    {
        if ($run->run_status !== 'CALCULATED') {
            throw new \RuntimeException(t('The payroll run must be CALCULATED before it can be verified.'));
        }

        $run->update([
            'run_status' => 'VERIFIED',
            'verification_method' => $method,
            'verified_sample_employee_ids' => $method === 'RANDOM_SAMPLE' ? $sampleEmployeeIds : null,
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
        ]);
    }

    // Mengunci presensi periode ini -- setelah CONFIRMED, calculate() ditolak.
    // Ditolak juga kalau masih ada payslip anomali (mis. pegawai tanpa rate
    // gaji aktif) -- HR harus selesaikan dulu, bukan lolos senyap ke pembayaran.
    public function confirm(PyrPayrollRun $run, string $confirmedBy): void
    {
        if ($run->run_status !== 'VERIFIED') {
            throw new \RuntimeException(t('The payroll run must be VERIFIED before it can be confirmed.'));
        }

        $anomalyCount = $run->payslips()->where('has_anomaly', true)->count();
        if ($anomalyCount > 0) {
            throw new \RuntimeException(str_replace(':n', $anomalyCount, t('There are still :n anomalous payslips (see pyr_payslip.anomaly_note) -- resolve them before confirming.')));
        }

        $run->update(['run_status' => 'CONFIRMED', 'confirmed_by' => $confirmedBy, 'confirmed_at' => now()]);
    }

    // Transfer bank / pembagian cash sudah dieksekusi di luar sistem (atau
    // via generator terpisah, belum dibangun) -- ini murni pencatatan status.
    public function markPaid(PyrPayrollRun $run, string $paidBy): void
    {
        if ($run->run_status !== 'CONFIRMED') {
            throw new \RuntimeException(t('The payroll run must be CONFIRMED before it can be paid.'));
        }

        $run->update(['run_status' => 'PAID', 'paid_by' => $paidBy, 'paid_at' => now()]);
    }

    // Slip elektronik dikirim / dicetak untuk yang cash -- pencatatan waktu
    // distribusi, belum membedakan kanal per pegawai (next iteration).
    public function markPayslipsSent(PyrPayrollRun $run): void
    {
        $run->update(['payslip_sent_at' => now()]);
    }

    // Tutup periode: masuk jurnal (hook JournalPostingService menyusul,
    // belum dipasang di sini), lalu auto-buat periode berikutnya kosong
    // (DRAFT) supaya "siap ke next periode" tanpa langkah manual tambahan.
    public function close(PyrPayrollRun $run, string $closedBy): PyrPayrollRun
    {
        if ($run->run_status !== 'PAID') {
            throw new \RuntimeException(t('The payroll run must be PAID before it can be closed.'));
        }

        $nextRun = PyrPayrollRun::firstOrCreate(
            ['period_type' => $run->period_type, 'period_start' => $run->nextPeriodStart()->toDateString()],
            ['period_end' => $run->nextPeriodEnd()->toDateString(), 'run_status' => 'DRAFT', 'triggered_by' => $closedBy, 'triggered_at' => now()],
        );

        $run->update(['run_status' => 'CLOSED', 'closed_by' => $closedBy, 'closed_at' => now(), 'next_run_id' => $nextRun->id_run]);

        return $nextRun;
    }

    private function calculateForEmployee(
        PyrPayrollRun $run,
        int $employeeId,
        Carbon $periodStart,
        Carbon $periodEnd,
        Collection $statusRules,
        Collection $dendaRules,
        Collection $overtimeRates,
        Collection $pph21Brackets,
        Collection $bpjsRates,
    ): void {
        $salary = PyrEmployeeSalary::activeFor($employeeId);
        if (! $salary) {
            // Dulu: diam-diam di-skip, pegawai "hilang" tanpa jejak dari run.
            // Sekarang: tetap dibuatkan payslip (angka 0) + ditandai anomali,
            // supaya HR lihat & lengkapi datanya, bukan menyadari belakangan
            // pas pegawai komplain tidak digaji.
            PyrPayslip::updateOrCreate(
                ['id_run' => $run->id_run, 'employee_id' => $employeeId],
                [
                    'total_pokok' => 0, 'total_tj_keahlian' => 0, 'total_tj_loyalitas' => 0, 'total_tj_prestasi' => 0,
                    'total_bonus_pagi' => 0, 'total_bonus_hadir' => 0, 'total_uang_roti' => 0, 'total_overtime_pay' => 0,
                    'total_denda' => 0, 'total_bpjs_employee' => 0, 'total_pph21' => 0, 'total_gaji_bersih' => 0,
                    'has_anomaly' => true, 'anomaly_note' => 'Belum ada pyr_employee_salary aktif utk pegawai ini',
                ],
            );

            return;
        }

        $dailyStatuses = AttDailyStatus::where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('attendance_date')
            ->get();

        $activeDisciplinaries = EmpDisciplinary::where('employee_id', $employeeId)
            ->whereIn('type', ['SP1', 'SP2', 'SP3'])
            ->get();

        $totals = array_fill_keys([...self::COMPONENTS, 'UANG_ROTI', 'OVERTIME', 'DENDA'], 0.0);
        $details = [];

        foreach ($dailyStatuses as $daily) {
            $spMultiplier = $this->applySpMultiplier($activeDisciplinaries, $daily);

            foreach (self::COMPONENTS as $component) {
                $amount = round($this->componentAmount($component, $daily, $salary, $statusRules) * $spMultiplier, 2);
                $totals[$component] += $amount;
                if ($amount != 0.0) {
                    $details[] = $this->detailRow($daily, $component, $amount);
                }
            }

            if ($daily->is_uang_roti) {
                $totals['UANG_ROTI'] += 5000;
                $details[] = $this->detailRow($daily, 'UANG_ROTI', 5000, 'Lembur > 2 jam, Staff/SPV Produksi (Tier 2 §4.4)');
            }

            $overtimePay = $this->overtimePay($daily, $salary, $overtimeRates);
            if ($overtimePay > 0) {
                $totals['OVERTIME'] += $overtimePay;
                $details[] = $this->detailRow($daily, 'OVERTIME', $overtimePay, "Lembur {$daily->overtime_minutes} menit ({$daily->overtime_type})");
            }

            $denda = $this->dendaFor($daily, $dendaRules);
            if ($denda > 0) {
                $totals['DENDA'] += $denda;
                $details[] = $this->detailRow($daily, 'DENDA', -$denda);
            }
        }

        // BPJS/PPh21 -- opt-in per pegawai (permintaan user 2026-09-07:
        // "tidak semua aktif mengikuti aturan ini"), dasar potongan dihitung
        // dari total periode ini (bukan per-hari, sesuai sifatnya sbg
        // potongan bulanan/periode, bukan harian).
        $bpjsAmount = 0.0;
        $pph21Amount = 0.0;

        if ($salary->bpjs_active) {
            $bpjsBase = $totals['POKOK'] + $totals['TJ_KEAHLIAN'] + $totals['TJ_LOYALITAS'] + $totals['TJ_PRESTASI'];
            $bpjs = $this->bpjsService->calculate($bpjsBase, $periodEnd, $bpjsRates);
            $bpjsAmount = $bpjs['employee']['total'];
            if ($bpjsAmount > 0) {
                $details[] = $this->periodDetailRow($periodEnd, 'BPJS', -$bpjsAmount, sprintf('JHT %s + JP %s + Kes %s', $bpjs['employee']['jht'], $bpjs['employee']['jp'], $bpjs['employee']['kes']));
            }
        }

        if ($salary->pph21_active && $salary->ptkp_status) {
            $pph21Gross = $totals['POKOK'] + $totals['TJ_KEAHLIAN'] + $totals['TJ_LOYALITAS'] + $totals['TJ_PRESTASI']
                + $totals['BONUS_PAGI'] + $totals['BONUS_HADIR'] + $totals['UANG_ROTI'] + $totals['OVERTIME'];
            $pph21 = $this->pph21Service->calculateTax($pph21Gross, $salary->ptkp_status, $periodEnd, $pph21Brackets);
            $pph21Amount = $pph21['tax_amount'];
            if ($pph21Amount > 0) {
                $details[] = $this->periodDetailRow($periodEnd, 'PPH21', -$pph21Amount, "TER Kategori {$pph21['category']} ({$pph21['rate_percentage']}%)");
            }
        }

        $totalGajiBersih = $totals['POKOK'] + $totals['TJ_KEAHLIAN'] + $totals['TJ_LOYALITAS'] + $totals['TJ_PRESTASI']
            + $totals['BONUS_PAGI'] + $totals['BONUS_HADIR'] + $totals['UANG_ROTI'] + $totals['OVERTIME']
            - $totals['DENDA'] - $bpjsAmount - $pph21Amount;

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
                'total_overtime_pay' => round($totals['OVERTIME'], 2),
                'total_denda' => round($totals['DENDA'], 2),
                'total_bpjs_employee' => round($bpjsAmount, 2),
                'total_pph21' => round($pph21Amount, 2),
                'total_gaji_bersih' => round($totalGajiBersih, 2),
                'has_anomaly' => false,
                'anomaly_note' => null,
            ],
        );

        $payslip->details()->delete();
        foreach ($details as $detail) {
            $detail['id_payslip'] = $payslip->id_payslip;
            PyrPayslipDetail::create($detail);
        }
    }

    private function componentAmount(string $component, AttDailyStatus $daily, PyrEmployeeSalary $salary, Collection $statusRules): float
    {
        $effect = $this->lookupStatusEffect($statusRules, $daily->status_code, $component, $daily->attendance_date);

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

    private function lookupStatusEffect(Collection $statusRules, string $statusCode, string $componentCode, $date): string
    {
        $rule = $statusRules->first(fn (PyrStatusRule $r) => $r->status_code === $statusCode
            && $r->component_code === $componentCode
            && $r->effective_from <= $date
            && (! $r->effective_to || $r->effective_to >= $date));

        return $rule->effect ?? 'FULL';
    }

    // Formula prorata Tier 2 §3 -- menit_hilang menggabungkan telat + pulang
    // awal + KJK ("PAT-5/PAT+5 akumulasi keduanya"), dibagi durasi_shift_menit.
    private function prorata(float $dailyRate, AttDailyStatus $daily): float
    {
        $shiftMinutes = $this->shiftDurationMinutes($daily);
        if ($shiftMinutes <= 0) {
            return $dailyRate;
        }

        $minutesHilang = $daily->minutes_late + $daily->minutes_early_leave + $daily->minutes_out_during_shift;
        $potongan = ($minutesHilang / $shiftMinutes) * $dailyRate;

        return max(0.0, round($dailyRate - $potongan, 2));
    }

    private function shiftDurationMinutes(AttDailyStatus $daily): int
    {
        if (! $daily->shift_start || ! $daily->shift_end) {
            return 0;
        }

        $start = Carbon::parse($daily->shift_start);
        $end = Carbon::parse($daily->shift_end);

        return $end->lt($start) ? $start->diffInMinutes($end->copy()->addDay()) : $start->diffInMinutes($end);
    }

    // Lembur per jam (HIGH IMPROVEMENT 2026-09-07, sebelumnya tidak ada sama
    // sekali) -- tarif/jam diturunkan dari gaji pokok harian dibagi durasi
    // shift AKTUAL hari itu (bukan angka "8 jam" hardcode, ikut shift
    // masing-masing pegawai), dikalikan multiplier data-driven pyr_overtime_rate
    // sesuai tipe hari (WORKDAY/EARLY/HOLIDAY/SUNDAY -- pola diadopsi dari
    // sp_process_attlogs sistem lama, docs/ddl/tmhrm.sql).
    private function overtimePay(AttDailyStatus $daily, PyrEmployeeSalary $salary, Collection $overtimeRates): float
    {
        if ($daily->overtime_minutes <= 0 || ! $daily->overtime_type) {
            return 0.0;
        }

        $shiftMinutes = $this->shiftDurationMinutes($daily);
        if ($shiftMinutes <= 0) {
            return 0.0;
        }

        $hourlyRate = ($salary->dailyBasicSalary() / $shiftMinutes) * 60;

        $rate = $overtimeRates->first(fn (PyrOvertimeRate $r) => $r->overtime_type === $daily->overtime_type
            && $r->effective_from <= $daily->attendance_date
            && (! $r->effective_to || $r->effective_to >= $daily->attendance_date));

        $multiplier = $rate ? (float) $rate->multiplier : 1.0;

        return round(($daily->overtime_minutes / 60) * $hourlyRate * $multiplier, 2);
    }

    private function dendaFor(AttDailyStatus $daily, Collection $dendaRules): float
    {
        $rule = $dendaRules->first(fn (PyrDendaRule $r) => $r->status_code === $daily->status_code
            && $r->effective_from <= $daily->attendance_date
            && (! $r->effective_to || $r->effective_to >= $daily->attendance_date));

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

    // BPJS/PPh21 dipotong per PERIODE (bukan per hari) -- pakai tanggal akhir
    // periode + status_code sintetis 'PERIODE' supaya tetap muat di skema
    // pyr_payslip_detail yang mewajibkan attendance_date+status_code.
    private function periodDetailRow(Carbon $periodEnd, string $componentCode, float $amount, ?string $note = null): array
    {
        return [
            'attendance_date' => $periodEnd->toDateString(),
            'status_code' => 'PERIODE',
            'component_code' => $componentCode,
            'amount' => $amount,
            'note' => $note,
        ];
    }
}
