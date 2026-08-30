<?php

namespace App\Services\Scheduling;

use App\Models\Prd\PrdWo;
use Illuminate\Support\Collection;

/**
 * Engine MPS — titik masuk otomatis untuk penjadwalan awal WO baru, dipanggil
 * dari WoCreationService (App\Services\Production\WoCreationService) tiap kali
 * WO dibuat. Parameter input = WO (due date + operations, sudah snapshot
 * routing per Master Card). Output = target_start/target_finish per operation
 * (kapan proses X di mesin Y mulai & selesai) -- dihitung & dipersist lewat
 * PdrEngine/BackwardSchedulingService yang sudah ada (algoritma Priority
 * Dispatching Rules, production_scheduling_tier1.md §5A).
 *
 * "Engine suggests, officer confirms" (CLAUDE.md §4): hasil dari sini cuma
 * membawa WO ke status SIMULATED, BUKAN SCHEDULED -- commit ke Master Schedule
 * real tetap keputusan manual PPIC (Scheduling Board / Kanban).
 */
class MpsEngine
{
    public function __construct(
        private readonly PdrEngine $pdrEngine,
    ) {}

    /**
     * @return Collection<int, \App\Models\Prd\PrdWoOperation> kosong bila WO belum
     *         siap dijadwal (tanpa due_date / tanpa operation) -- dibiarkan DRAFT,
     *         dijadwalkan manual lewat Scheduling Board seperti alur yang sudah ada.
     */
    public function runForWo(PrdWo $wo, string $triggeredBy): Collection
    {
        if (! $wo->due_date || $wo->operations->isEmpty()) {
            return collect();
        }

        try {
            $tasks = $this->pdrEngine->backwardScheduleAndPersist($wo);
        } catch (\RuntimeException) {
            // Rate Band tak resolvable (histori & master kosong) -- bukan error
            // fatal, biarkan WO tetap DRAFT untuk dijadwalkan manual PPIC/Plant
            // Manager, sama seperti kalau tombol Scheduling Board yang gagal.
            return collect();
        }

        if ($wo->canTransitionTo('SIMULATED')) {
            $wo->transitionTo('SIMULATED');
        }

        return $tasks;
    }
}
