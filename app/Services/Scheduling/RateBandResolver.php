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
 * CATATAN IMPLEMENTASI: query historis mengasumsikan qty & durasi aktual
 * tersimpan di prd_wo_operation (actual_start/actual_finish/qty) untuk task
 * yang sudah DONE/QC_PASSED. Sesuaikan bila skema Production Output
 * Recording (F15) akhirnya disimpan di tabel terpisah.
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
        $historical = $this->historicalRate($machineId, $processName);

        if ($historical !== null && $historical['datapoint_count'] >= $this->minDataPoint) {
            return $historical['rate'];
        }

        return $this->masterFallbackRate($machineId, $processName);
    }

    private function historicalRate(int $machineId, string $processName): ?array
    {
        $rows = PrdWoOperation::query()
            ->where('machine_id', $machineId)
            ->where('process_name', $processName)
            ->whereIn('task_status', ['DONE', 'QC_PASSED'])
            ->whereNotNull('actual_start')
            ->whereNotNull('actual_finish')
            ->select(['qty', 'actual_start', 'actual_finish'])
            ->limit(200) // rolling window praktis, jangan tarik seluruh histori tanpa batas
            ->orderByDesc('actual_finish')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $rates = $rows->map(function ($row) {
            $hours = $row->actual_start->diffInMinutes($row->actual_finish, true) / 60;

            return $hours > 0 ? ((float) $row->qty / $hours) : null;
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
