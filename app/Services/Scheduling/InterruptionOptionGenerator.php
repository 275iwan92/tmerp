<?php

namespace App\Services\Scheduling;

use App\Models\Prd\PrdDecisionFeedbackLog;
use App\Models\Prd\PrdWoOperation;
use Carbon\Carbon;

/**
 * Interruption/Hot Job — hasilkan 1-3 opsi penyisipan dengan dampak terukur,
 * BUKAN 1 jawaban otomatis. Sumber: production_scheduling_tier1.md §5A.2,
 * production_scheduling_tier2.md §5.
 */
class InterruptionOptionGenerator
{
    public function __construct(
        private readonly PdrEngine $pdrEngine,
    ) {}

    /**
     * @return InterruptionOption[] tidak selalu 3 — hanya opsi valid yang ditampilkan
     */
    public function generateOptions(PrdWoOperation $hotTask, int $machineId, string $processName): array
    {
        $options = [];

        $queue = $this->pdrEngine->buildResourceQueue($machineId, $processName);
        $displaced = $queue->filter(fn (PrdWoOperation $t) => $t->operation_id !== $hotTask->operation_id);

        foreach ($displaced->take(2) as $candidate) {
            $impactHours = $this->estimateShiftImpact($candidate, $hotTask);
            $stillSafe = $this->stillWithinDueDate($candidate, $impactHours);

            $options[] = new InterruptionOption(
                label: $stillSafe ? 'Sisip, geser '.$candidate->wo?->wo_no.' (aman)' : 'Sisip, geser '.$candidate->wo?->wo_no.' (BERISIKO)',
                displacedOperationId: $candidate->operation_id,
                impactHours: $impactHours,
                isSafe: $stillSafe,
                estimatedCost: null,
            );
        }

        // Opsi reroute subkontrak selalu ditawarkan sebagai fallback ke-3 (Tier1 §9.3 —
        // wajib PO tipe SUBCONTRACT bila dipilih; estimasi biaya diisi caller/UI, bukan
        // dihitung di sini karena butuh data vendor rate yang di luar scope engine ini).
        $options[] = new InterruptionOption(
            label: 'Reroute subkontrak — tidak ada job lain tergeser',
            displacedOperationId: null,
            impactHours: 0.0,
            isSafe: true,
            estimatedCost: null,
            requiresSubcontractPo: true,
        );

        return $options;
    }

    public function recordDecision(
        string $eventType,
        ?int $woId,
        ?int $customerId,
        ?int $machineId,
        array $optionsPresented,
        string $optionChosen,
        string $chosenBy,
    ): PrdDecisionFeedbackLog {
        return PrdDecisionFeedbackLog::create([
            'event_type' => $eventType,
            'wo_id' => $woId,
            'context_customer_id' => $customerId,
            'context_machine_id' => $machineId,
            'options_presented' => $optionsPresented,
            'option_chosen' => $optionChosen,
            'chosen_by' => $chosenBy,
            'chosen_at' => Carbon::now(),
        ]);
    }

    private function estimateShiftImpact(PrdWoOperation $candidate, PrdWoOperation $hotTask): float
    {
        if (! $candidate->target_start || ! $hotTask->target_start || ! $hotTask->target_finish) {
            return 0.0;
        }

        // Perkiraan sederhana: candidate digeser sebesar durasi hot task
        return $hotTask->target_start->diffInMinutes($hotTask->target_finish, true) / 60;
    }

    private function stillWithinDueDate(PrdWoOperation $candidate, float $shiftHours): bool
    {
        if (! $candidate->target_finish || ! $candidate->wo?->due_date) {
            return true;
        }
        $newFinish = $candidate->target_finish->copy()->addHours($shiftHours);

        return $newFinish->lte($candidate->wo->due_date);
    }
}
