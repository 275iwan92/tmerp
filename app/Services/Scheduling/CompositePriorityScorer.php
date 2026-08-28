<?php

namespace App\Services\Scheduling;

use App\Models\Prd\PrdWoOperation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Composite Priority Score — Hot Job -> Critical Ratio -> EDD -> Resource Fit -> FIFO.
 * Sumber: production_scheduling_tier1.md §5A.4, production_scheduling_tier2.md §4.2.
 *
 * GA (Genetic Algorithm) ditolak untuk domain ini (lihat Tier1 §5A) — scorer ini
 * murni aturan berlapis, cepat & transparan, cocok untuk local-repair real-time.
 */
class CompositePriorityScorer
{
    public function __construct(
        private readonly CrewAvailabilityChecker $crewChecker,
    ) {}

    public function score(PrdWoOperation $task, Carbon $now, array $proposedCrewIds = []): PriorityScore
    {
        if ($task->is_hot_job) {
            return new PriorityScore(
                layer: 0,
                tiebreak: $this->criticalRatio($task, $now),
                label: 'HOT_JOB',
            );
        }

        $cr = $this->criticalRatio($task, $now);

        if ($cr < 1.0) {
            return new PriorityScore(
                layer: 1,
                tiebreak: $cr, // makin kecil makin darurat
                label: 'AT_RISK_CR<1',
            );
        }

        return new PriorityScore(
            layer: 2,
            tiebreak: $task->target_finish?->getTimestamp() ?? PHP_INT_MAX, // EDD
            resourceFitPenalty: $this->resourceFitPenalty($task, $proposedCrewIds),
            fifoTiebreak: $task->wo?->confirmed_at?->getTimestamp() ?? PHP_INT_MAX,
            label: 'NORMAL_EDD',
        );
    }

    /**
     * @param  Collection<int, PrdWoOperation>  $candidates
     * @return Collection<int, PrdWoOperation> terurut, siap diambil ->first()
     */
    public function rank(Collection $candidates, Carbon $now): Collection
    {
        return $candidates
            ->map(fn (PrdWoOperation $t) => [
                'task' => $t,
                'score' => $this->score($t, $now, $this->extractProposedCrewIds($t)),
            ])
            ->sortBy(fn ($row) => [
                $row['score']->layer,
                $row['score']->tiebreak,
                $row['score']->resourceFitPenalty ?? 0,
                $row['score']->fifoTiebreak ?? 0,
            ])
            ->pluck('task')
            ->values();
    }

    private function criticalRatio(PrdWoOperation $task, Carbon $now): float
    {
        if (! $task->target_finish) {
            return PHP_FLOAT_MAX; // belum dijadwalkan sama sekali -> jangan dianggap darurat palsu
        }

        $remainingWork = $this->remainingWorkHours($task);
        if ($remainingWork <= 0) {
            return 0.0; // tak ada sisa kerja tapi belum selesai -> maksimal darurat
        }

        $timeLeftHours = $now->diffInMinutes($task->target_finish, false) / 60;

        return round($timeLeftHours / $remainingWork, 4);
    }

    /**
     * Sisa waktu proses dari task ini sampai task terakhir di routing WO yang sama.
     */
    private function remainingWorkHours(PrdWoOperation $task): float
    {
        if (! $task->wo) {
            return 1.0; // guard, hindari division by zero di pemanggil
        }

        return $task->wo->operations
            ->where('seq_no', '>=', $task->seq_no)
            ->sum(function (PrdWoOperation $t) {
                if ($t->target_start && $t->target_finish) {
                    return $t->target_start->diffInMinutes($t->target_finish, true) / 60;
                }

                return 0;
            }) ?: 1.0;
    }

    private function resourceFitPenalty(PrdWoOperation $task, array $proposedCrewIds): int
    {
        // Lapis 3: hindari resource lambat kalau ada alternatif lebih cepat tersedia segera,
        // DAN wajib cek crew tersedia (§5A.9) — bukan cuma cek mesin kosong.
        if (! empty($proposedCrewIds) && ! $this->crewChecker->isAvailable($task, $proposedCrewIds)) {
            return 1_000_000; // PENALTY_BLOCK — task ini tak boleh dipilih sampai crew free
        }

        // TODO Tier2 lanjutan: baca flag Slow Machine (F9) dari histori Rate Band,
        // beri penalty menengah (bukan blok total) bila ada resource lebih cepat tersedia
        // dalam window wajar. Belum diimplementasi di sini — placeholder aman (0).
        return 0;
    }

    private function extractProposedCrewIds(PrdWoOperation $task): array
    {
        return $task->relationLoaded('crew')
            ? $task->crew->pluck('employee_id')->all()
            : [];
    }
}
