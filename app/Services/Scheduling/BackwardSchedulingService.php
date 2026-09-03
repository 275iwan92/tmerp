<?php

namespace App\Services\Scheduling;

use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;

/**
 * Backward Scheduling — hitung mundur dari due date, per operation, sesuai
 * kemampuan/target tiap proses (Rate Band).
 *
 * Sumber: production_scheduling_tier1.md §5A.3, production_scheduling_tier2.md §4.1.
 *
 * Dipanggil saat:
 *  - WO masuk Simulation Layer (Tier1 §5A.1)
 *  - due date berubah
 *  - ada interupsi yang menggeser Task (Tier1 §5A.2)
 *  - Rate Band ter-update dari data baru (§5A.6)
 */
class BackwardSchedulingService
{
    public function __construct(
        private readonly RateBandResolver $rateBandResolver,
        private readonly MachineCalendarService $calendar,
    ) {}

    /**
     * @return Collection<int, PrdWoOperation> operations dengan target_start/target_finish
     *         terisi (belum di-save — caller yang memutuskan persist, supaya bisa dipakai
     *         juga untuk Simulation Layer tanpa menyentuh data real).
     */
    public function schedule(PrdWo $wo): Collection
    {
        $tasks = $wo->operations()->orderByDesc('seq_no')->get(); // mundur dari task terakhir

        $cursor = $wo->due_date instanceof Carbon ? $wo->due_date->copy() : Carbon::parse($wo->due_date);

        foreach ($tasks as $task) {
            $rate = $this->resolveRateForTask($task);
            $durationHours = $rate > 0 ? ((float) $task->qty / $rate) : 0.0;
            $durationInterval = CarbonInterval::seconds((int) round($durationHours * 3600));

            $targetFinish = $cursor->copy();
            $targetStart = $this->calendar->subtractWorkingTime($targetFinish, $durationInterval, $task->machine_id);

            $task->target_finish = $targetFinish;
            $task->target_start = $targetStart;
            $task->rate_band_value = $rate; // snapshot, untuk audit (Tier3 §3)

            $cursor = $targetStart->copy(); // task sebelumnya harus selesai di titik ini
        }

        // material_needed_by = target_start task PERTAMA (setelah re-sort urutan asli seq_no ASC)
        $firstTask = $tasks->sortBy('seq_no')->first();
        if ($firstTask) {
            $wo->material_needed_by = $firstTask->target_start;
        }

        return $tasks->sortBy('seq_no')->values();
    }

    /**
     * Persist hasil schedule() — dipisah dari schedule() supaya Simulation Layer
     * bisa panggil schedule() saja tanpa commit (Tier1 §5A.1).
     */
    public function scheduleAndPersist(PrdWo $wo): Collection
    {
        $tasks = $this->schedule($wo);

        foreach ($tasks as $task) {
            $task->save();
        }
        $wo->save();

        return $tasks;
    }

    private function resolveRateForTask(PrdWoOperation $task): float
    {
        if ($task->resource_type === 'MACHINE' && $task->machine_id) {
            return $this->rateBandResolver->resolve((int) $task->machine_id, $task->process_name);
        }

        // Manual job (docs/BACKLOG.md §6, FIX 2026-09-02) -- rate = properti proses
        // (histori Production Output difilter resource_type=MANUAL+process_name),
        // BUKAN per-operator individual (itu tetap gap terbuka, butuh desain agregasi
        // rate crew multi-orang yang belum diputuskan -- lihat docblock resolveManual()).
        return $this->rateBandResolver->resolveManual($task->process_name);
    }
}
