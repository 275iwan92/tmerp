<?php

namespace App\Services\Scheduling;

use App\Models\Prd\PrdWoOperation;
use Illuminate\Support\Facades\DB;

/**
 * Rate Band — kecepatan standar per resource+proses.
 * Sumber: production_scheduling_tier1.md §5A.6, §4 istilah baku.
 *
 * Nilai default dihitung dari histori Production Output Recording (F15),
 * BUKAN angka statis di-hardcode. Fallback ke master data manual (kolom
 * `max_speed_per_hour` di tm2mst.prod_machine) bila data historis belum
 * cukup (di bawah `rate_band_min_datapoint`).
 *
 * FIX 2026-09-03 (docs/BACKLOG.md §6): rate historis sekarang dihitung dari
 * qty_good AKTUAL (`prd_wo_operation_output`, F15 Production Output
 * Recording -- sudah ada datanya real sejak F15 dibangun) dibagi durasi
 * aktual -- BUKAN lagi qty TARGET operasi (`prd_wo_operation.qty`, rencana,
 * bisa beda dari hasil nyata krn reject/waste). Fallback ke qty target HANYA
 * kalau task DONE/QC_PASSED tapi genuinely 0 baris F15 tercatat (data
 * legacy/gap pencatatan, bukan kasus normal).
 */
class RateBandResolver
{
    public function __construct(
        private int $minDataPoint = 5,
    ) {
        $this->minDataPoint = $minDataPoint ?: config('production_scheduling.rate_band_min_datapoint', 5);
    }

    /**
     * @return float unit per jam
     */
    public function resolve(int $machineId, string $processName): float
    {
        $historical = $this->historicalRate($processName, machineId: $machineId);

        if ($historical !== null && $historical['datapoint_count'] >= $this->minDataPoint) {
            return $historical['rate'];
        }

        return $this->masterFallbackRate($machineId, $processName);
    }

    /**
     * Rate Band utk task MANUAL (resource_type=MANUAL, tanpa machine_id) --
     * docs/BACKLOG.md §6 "Rate Band MANUAL masih throw error". Sebelumnya
     * SELALU throw (0% task manual bisa di-backward-schedule). Sekarang pakai
     * histori Production Output yang sama seperti jalur MACHINE, cuma
     * di-filter resource_type=MANUAL & process_name (BUKAN per-employee --
     * itu tetap gap terbuka, butuh desain agregasi rate crew multi-orang yang
     * belum diputuskan; di sini rate = properti inheren proses tsb, sama
     * spirit-nya dgn "1 mesin = 1 rate" tapi utk "1 proses manual = 1 rate").
     * Fallback: exception jelas (bukan angka ngasal) kalau histori < minDataPoint --
     * PPIC/Plant Manager perlu catat cukup Production Output F15 dulu.
     *
     * @return float unit per jam
     */
    public function resolveManual(string $processName): float
    {
        $historical = $this->historicalRate($processName, resourceType: 'MANUAL');

        if ($historical !== null && $historical['datapoint_count'] >= $this->minDataPoint) {
            return $historical['rate'];
        }

        $found = $historical['datapoint_count'] ?? 0;

        throw new \RuntimeException(
            "Rate Band manual utk proses '{$processName}' belum punya cukup histori Production Output ".
            "(butuh minimal {$this->minDataPoint} data poin DONE/QC_PASSED, ada {$found}) — ".
            'catat Production Output (F15) dulu, atau PPIC/Plant Manager input estimasi manual sementara.'
        );
    }

    private function historicalRate(string $processName, ?int $machineId = null, ?string $resourceType = null): ?array
    {
        $rows = PrdWoOperation::query()
            ->when($machineId !== null, fn ($q) => $q->where('machine_id', $machineId))
            ->when($resourceType !== null, fn ($q) => $q->where('resource_type', $resourceType))
            ->where('process_name', $processName)
            ->whereIn('task_status', ['DONE', 'QC_PASSED'])
            ->whereNotNull('actual_start')
            ->whereNotNull('actual_finish')
            ->select(['operation_id', 'qty', 'actual_start', 'actual_finish'])
            ->with('outputs:operation_id,qty_good')
            ->limit(200) // rolling window praktis, jangan tarik seluruh histori tanpa batas
            ->orderByDesc('actual_finish')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $rates = $rows->map(function ($row) {
            $hours = $row->actual_start->diffInMinutes($row->actual_finish, true) / 60;

            if ($hours <= 0) {
                return null;
            }

            // qty_good aktual (F15) diutamakan; fallback qty target hanya kalau
            // genuinely tak ada 1 pun baris Production Output tercatat.
            $qtyGood = (float) $row->outputs->sum('qty_good');
            $qty = $qtyGood > 0 ? $qtyGood : (float) $row->qty;

            return $qty / $hours;
        })->filter();

        if ($rates->isEmpty()) {
            return null;
        }

        return [
            'rate' => round($rates->avg(), 4),
            'datapoint_count' => $rates->count(),
        ];
    }

    /**
     * Fallback master data manual — tm2mst.prod_machine.max_speed_per_hour.
     */
    private function masterFallbackRate(int $machineId, string $processName): float
    {
        $master = DB::connection('tm2mst')->table('prod_machine')
            ->where('machine_id', $machineId)
            ->first();

        if ($master && isset($master->max_speed_per_hour) && $master->max_speed_per_hour > 0) {
            // Ambil rate konservatif: 80% dari kecepatan maksimum sebagai default aman
            // (guard kualitas, Tier1 prinsip #3 — jangan langsung pakai kecepatan max).
            return round($master->max_speed_per_hour * 0.8, 4);
        }

        throw new \RuntimeException(
            "Rate Band tidak ditemukan untuk machine_id={$machineId}, proses={$processName} — ".
            'baik histori maupun master data kosong. Perlu input manual PPIC/Plant Manager.'
        );
    }
}
