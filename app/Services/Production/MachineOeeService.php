<?php

namespace App\Services\Production;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

// OEE AKTUAL (bukan target). OEE = Availability x Performance x Quality.
//
// Sampel HTML user hanya punya kotak "Target OEE" -- angka yang diketik
// manual dan tidak pernah dibandingkan dengan kenyataan. Service ini
// menghitung sisi kenyataannya dari data yang memang sudah ada:
//   Availability : kalender mesin (shift x jam) dikurangi downtime TAK
//                  terencana dari prod_machine_downtime
//   Performance  : output aktual vs (jam jalan x speed_target_per_hour)
//   Quality      : qty bagus vs qty bagus + spoilage + waste
//
// ATURAN KERAS: tiap faktor yang datanya belum ada dikembalikan NULL beserta
// alasannya, dan oee TOTAL juga NULL. OEE 0% dan OEE "belum bisa dihitung"
// adalah dua hal yang sangat berbeda untuk manajer produksi -- yang pertama
// berarti mesin mati, yang kedua berarti pencatatan belum jalan. Service ini
// tidak pernah menukar yang kedua jadi yang pertama.
class MachineOeeService
{
    // Hari kerja per minggu dipakai menghitung jam tersedia; 6 hari = praktik
    // pabrik saat ini (Senin-Sabtu).
    private const WORK_DAYS_PER_WEEK = 6;

    public function calculate(int $machineId, CarbonInterface $from, CarbonInterface $to): array
    {
        $machine = ProdMachine::find($machineId);

        if (! $machine) {
            return $this->emptyResult('Mesin tidak ditemukan.');
        }

        $plannedMinutes = $this->plannedMinutes($machine, $from, $to);
        $downtime = $this->downtimeMinutes($machineId, $from, $to);
        $production = $this->productionTotals($machineId, $from, $to);

        $availability = $this->availability($plannedMinutes, $downtime['unplanned']);
        $performance = $this->performance($machine, $production);
        $quality = $this->quality($production);

        $factors = [
            'availability' => $availability,
            'performance' => $performance,
            'quality' => $quality,
        ];

        // Total hanya dihitung kalau KETIGA faktor ada. Mengalikan 2 faktor
        // dan menyebutnya OEE adalah kesalahan klasik yang membuat angka
        // terlihat bagus padahal satu dimensi tidak terukur.
        $complete = ! in_array(null, array_map(fn ($f) => $f['value'], $factors), true);

        return [
            'machine' => $machine,
            'period' => ['from' => $from->copy(), 'to' => $to->copy()],
            'planned_minutes' => $plannedMinutes,
            'downtime' => $downtime,
            'production' => $production,
            'factors' => $factors,
            'oee' => $complete
                ? round($availability['value'] * $performance['value'] * $quality['value'] / 10000, 1)
                : null,
            'oee_target' => $machine->oee_target_pct !== null ? (float) $machine->oee_target_pct : null,
            'missing' => collect($factors)->filter(fn ($f) => $f['value'] === null)
                ->map(fn ($f) => $f['reason'])->all(),
        ];
    }

    // Jam tersedia menurut kalender mesin. NULL bila kalender belum diisi --
    // jangan pernah mengasumsikan 24/7, itu membuat Availability terlihat
    // jauh lebih buruk daripada kenyataan.
    private function plannedMinutes(ProdMachine $m, CarbonInterface $from, CarbonInterface $to): ?int
    {
        if (! $m->shifts_per_day || ! $m->hours_per_shift) {
            return null;
        }

        $days = max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $workDays = (int) round($days * self::WORK_DAYS_PER_WEEK / 7);

        return (int) round($workDays * $m->shifts_per_day * (float) $m->hours_per_shift * 60);
    }

    private function downtimeMinutes(int $machineId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = ProdMachineDowntime::query()
            ->where('machine_id', $machineId)
            ->where('start_at', '<=', $to)
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', $from))
            ->get();

        $planned = 0;
        $unplanned = 0;
        $byKind = [];

        foreach ($rows as $d) {
            // Stop yang melewati batas periode dipotong, bukan dihitung penuh.
            $start = $d->start_at->max($from);
            $end = ($d->end_at ?? now())->min($to);
            $minutes = $end->greaterThan($start) ? (int) $start->diffInMinutes($end) : 0;

            $d->is_planned ? $planned += $minutes : $unplanned += $minutes;
            $byKind[$d->downtime_kind] = ($byKind[$d->downtime_kind] ?? 0) + $minutes;
        }

        arsort($byKind);

        return [
            'planned' => $planned,
            'unplanned' => $unplanned,
            'total' => $planned + $unplanned,
            'by_kind' => $byKind,
            'open_events' => $rows->filter(fn ($d) => $d->isOpen())->count(),
        ];
    }

    // Output & jam jalan dari operasi WO yang sudah selesai di mesin ini.
    private function productionTotals(int $machineId, CarbonInterface $from, CarbonInterface $to): array
    {
        $ops = DB::connection('tm2prd')->table('prd_wo_operation')
            ->where('machine_id', $machineId)
            ->whereNotNull('actual_start')
            ->whereNotNull('actual_finish')
            ->whereBetween('actual_start', [$from, $to])
            ->get(['operation_id', 'qty', 'spoilage_qty', 'waste_qty', 'actual_start', 'actual_finish']);

        $runMinutes = 0;
        $qtyPlanned = 0.0;
        $spoilage = 0.0;
        $waste = 0.0;

        foreach ($ops as $op) {
            $runMinutes += (int) Carbon::parse($op->actual_start)
                ->diffInMinutes(Carbon::parse($op->actual_finish), true);
            $qtyPlanned += (float) $op->qty;
            $spoilage += (float) $op->spoilage_qty;
            $waste += (float) $op->waste_qty;
        }

        // Output bagus yang tercatat resmi ada di prd_wo_operation_output;
        // kalau operator belum mengisinya, JANGAN diganti qty rencana --
        // itu akan membuat Quality selalu terlihat sempurna.
        $qtyGood = $ops->isEmpty() ? null : (float) DB::connection('tm2prd')
            ->table('prd_wo_operation_output')
            ->whereIn('operation_id', $ops->pluck('operation_id'))
            ->sum('qty_good');

        $outputRows = $ops->isEmpty() ? 0 : DB::connection('tm2prd')
            ->table('prd_wo_operation_output')
            ->whereIn('operation_id', $ops->pluck('operation_id'))
            ->count();

        return [
            'operations' => $ops->count(),
            'run_minutes' => $runMinutes,
            'qty_planned' => $qtyPlanned,
            'qty_good' => $outputRows > 0 ? $qtyGood : null,
            'output_rows' => $outputRows,
            'spoilage_qty' => $spoilage,
            'waste_qty' => $waste,
        ];
    }

    private function availability(?int $plannedMinutes, int $unplannedDowntime): array
    {
        if (! $plannedMinutes) {
            return $this->factor(null, 'Kalender mesin belum diisi (shift/hari & jam/shift) — jam tersedia tidak diketahui.');
        }

        return $this->factor(
            round(max(0, $plannedMinutes - $unplannedDowntime) / $plannedMinutes * 100, 1),
            null
        );
    }

    private function performance(ProdMachine $m, array $production): array
    {
        if (! $m->speed_target_per_hour) {
            return $this->factor(null, 'Target kecepatan aktual mesin belum diisi — acuan Performance tidak ada.');
        }

        if ($production['run_minutes'] <= 0) {
            return $this->factor(null, 'Belum ada operasi WO selesai di mesin ini pada periode tsb.');
        }

        if ($production['qty_good'] === null) {
            return $this->factor(null, 'Output per operasi belum dicatat operator (prd_wo_operation_output kosong).');
        }

        $theoretical = $production['run_minutes'] / 60 * $m->speed_target_per_hour;

        return $this->factor(
            $theoretical > 0 ? round(min(100, $production['qty_good'] / $theoretical * 100), 1) : null,
            $theoretical > 0 ? null : 'Kapasitas teoretis nol.'
        );
    }

    private function quality(array $production): array
    {
        if ($production['qty_good'] === null) {
            return $this->factor(null, 'Output bagus belum dicatat — Quality tidak bisa dihitung dari qty rencana.');
        }

        $total = $production['qty_good'] + $production['spoilage_qty'] + $production['waste_qty'];

        if ($total <= 0) {
            return $this->factor(null, 'Total produksi nol pada periode tsb.');
        }

        return $this->factor(round($production['qty_good'] / $total * 100, 1), null);
    }

    private function factor(?float $value, ?string $reason): array
    {
        return ['value' => $value, 'reason' => $reason];
    }

    private function emptyResult(string $reason): array
    {
        return [
            'machine' => null,
            'factors' => [
                'availability' => $this->factor(null, $reason),
                'performance' => $this->factor(null, $reason),
                'quality' => $this->factor(null, $reason),
            ],
            'oee' => null,
            'oee_target' => null,
            'missing' => [$reason],
            'planned_minutes' => null,
            'downtime' => ['planned' => 0, 'unplanned' => 0, 'total' => 0, 'by_kind' => [], 'open_events' => 0],
            'production' => ['operations' => 0, 'run_minutes' => 0, 'qty_planned' => 0, 'qty_good' => null,
                'output_rows' => 0, 'spoilage_qty' => 0, 'waste_qty' => 0],
            'period' => null,
        ];
    }

    // MTTR/MTBF aktual dari log downtime -- pembanding nyata untuk
    // prod_machine.mtbf_hours yang diisi manual dari buku manual mesin.
    public function reliability(int $machineId, CarbonInterface $from, CarbonInterface $to): array
    {
        $breakdowns = ProdMachineDowntime::query()
            ->where('machine_id', $machineId)
            ->where('is_planned', false)
            ->where('downtime_kind', 'BREAKDOWN')
            ->whereBetween('start_at', [$from, $to])
            ->whereNotNull('end_at')
            ->get();

        if ($breakdowns->isEmpty()) {
            return ['failures' => 0, 'mttr_hours' => null, 'mtbf_hours' => null,
                'reason' => 'Belum ada kejadian breakdown tercatat pada periode ini.'];
        }

        $repairMinutes = (int) $breakdowns->sum('duration_minutes');
        $periodHours = $from->copy()->diffInHours($to->copy(), true);
        $upHours = max(0, $periodHours - $repairMinutes / 60);

        return [
            'failures' => $breakdowns->count(),
            'mttr_hours' => round($repairMinutes / 60 / $breakdowns->count(), 2),
            'mtbf_hours' => round($upHours / $breakdowns->count(), 2),
            'reason' => null,
        ];
    }
}
