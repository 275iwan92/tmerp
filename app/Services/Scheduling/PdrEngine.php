<?php

namespace App\Services\Scheduling;

use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * PDR Engine — titik masuk TUNGGAL untuk penjadwalan Production Scheduling.
 * Ini yang dipanggil dari Livewire component, bukan service individual di
 * bawahnya secara langsung (kecuali butuh kontrol lebih detail).
 *
 * Sumber: production_scheduling_tier1.md §5A (seluruh subsection),
 *         production_scheduling_tier2.md §4 (spesifikasi implementasi).
 *
 * Alasan GA ditolak & PDR dipilih: lihat Tier1 §5A pembuka — cepat, transparan,
 * natural untuk local-repair, sesuai kebutuhan PPIC real-time.
 */
class PdrEngine
{
    public function __construct(
        private readonly BackwardSchedulingService $backwardScheduler,
        private readonly CompositePriorityScorer $scorer,
        private readonly SchedulingRuleEngine $ruleEngine,
        private readonly CrewAvailabilityChecker $crewChecker,
    ) {}

    /**
     * Bangun antrean task yang menunggu untuk SATU resource (mesin), sudah
     * lewat Batching -> Priority Score -> Sequencing (Tier2 §4.2 "Urutan eksekusi").
     *
     * Ini yang dipanggil Scheduling Board untuk menampilkan "next up" per mesin,
     * atau untuk auto-suggest saat resource baru free.
     *
     * @return Collection<int, PrdWoOperation> task/grup task terurut, index 0 = prioritas tertinggi
     */
    public function buildResourceQueue(int $machineId, string $processName, ?Carbon $now = null): Collection
    {
        $now ??= Carbon::now();

        $candidates = PrdWoOperation::query()
            ->where('machine_id', $machineId)
            ->where('process_name', $processName)
            ->where('task_status', 'PENDING')
            ->with(['wo', 'crew'])
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $groups = $this->ruleEngine->applyBatching($candidates, $machineId, $processName);

        // Skor tiap grup berdasarkan anggota "termewah" prioritasnya (representatif grup)
        $rankedGroups = $groups
            ->map(fn (Collection $group) => [
                'group' => $group,
                'score' => $this->scorer->rank($group, $now)->first()
                    ? $this->scorer->score($group->first(), $now, $this->crewIdsOf($group->first()))
                    : null,
            ])
            ->filter(fn ($row) => $row['score'] !== null)
            ->sortBy(fn ($row) => [
                $row['score']->layer,
                $row['score']->tiebreak,
                $row['score']->resourceFitPenalty ?? 0,
                $row['score']->fifoTiebreak ?? 0,
            ])
            ->pluck('group');

        $flatSorted = $rankedGroups->flatten(1);

        return $this->ruleEngine->applySequencing($flatSorted, $machineId, $processName);
    }

    /**
     * Ambil satu task berikutnya yang paling berhak dapat resource ini SEKARANG,
     * lengkap dengan pengecekan crew (Lapis 3, Tier1 §5A.9). Mengembalikan null
     * kalau task teratas ternyata crew-nya tak tersedia (PENALTY_BLOCK) — caller
     * (Livewire) yang memutuskan tindak lanjut (skip ke kandidat lain secara
     * manual, atau tampilkan alasan ke PPIC).
     */
    public function pickNext(int $machineId, string $processName, array $proposedCrewIds = [], ?Carbon $now = null): ?PrdWoOperation
    {
        $queue = $this->buildResourceQueue($machineId, $processName, $now);
        $top = $queue->first();

        if (! $top) {
            return null;
        }

        if (! empty($proposedCrewIds) && ! $this->crewChecker->isAvailable($top, $proposedCrewIds)) {
            return null; // blocked — lihat conflictingEmployees() untuk detail alasan
        }

        return $top;
    }

    /**
     * Jalankan Backward Scheduling untuk satu WO — dipakai saat WO masuk
     * Simulation Layer (Tier1 §5A.1) atau saat due date/rate berubah.
     * TIDAK melakukan persist — panggil ->each->save() sendiri di caller,
     * atau pakai scheduleAndPersist() di bawah untuk commit langsung.
     */
    public function backwardSchedule(PrdWo $wo): Collection
    {
        return $this->backwardScheduler->schedule($wo);
    }

    public function backwardScheduleAndPersist(PrdWo $wo): Collection
    {
        return $this->backwardScheduler->scheduleAndPersist($wo);
    }

    /**
     * Validasi sebelum commit perubahan manual dari Scheduling Board (drag/resize/
     * reassign) — dipanggil Livewire component SEBELUM menyimpan perubahan posisi.
     * Mengembalikan array kosong bila valid, atau daftar alasan penolakan bila tidak.
     *
     * @return string[] daftar pesan error (kosong = valid)
     */
    public function validateManualReschedule(PrdWoOperation $task, Carbon $newStart, Carbon $newFinish, array $proposedCrewIds = []): array
    {
        $errors = [];

        if ($newFinish->lte($newStart)) {
            $errors[] = t('The finish time must be after the start time.');
        }

        $minMinutes = config('production_scheduling.min_task_duration_minutes', 30);
        if ($newStart->diffInMinutes($newFinish, true) < $minMinutes) {
            $errors[] = tf('The task duration is at least :n minutes.', ['n' => $minMinutes]);
        }

        if (! empty($proposedCrewIds)) {
            // Cek konflik pakai window waktu BARU (bukan window lama task)
            $probe = clone $task;
            $probe->target_start = $newStart;
            $probe->target_finish = $newFinish;

            $conflicts = $this->crewChecker->conflictingEmployees($probe, $proposedCrewIds);
            if (! empty($conflicts)) {
                $errors[] = tf('The operator clashes with another task in this time window (employee_id: :ids).',
                    ['ids' => implode(', ', $conflicts)]);
            }
        }

        if ($task->task_status === 'QC_HOLD') {
            $errors[] = t('A task with status QC_HOLD is locked until QA decides (Tier4 §6).');
        }

        return $errors;
    }

    private function crewIdsOf(PrdWoOperation $task): array
    {
        return $task->relationLoaded('crew') ? $task->crew->pluck('employee_id')->all() : [];
    }
}
