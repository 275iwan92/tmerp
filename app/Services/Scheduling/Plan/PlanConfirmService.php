<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use App\Models\Prd\PrdPlanDecision;
use App\Models\Prd\PrdPlanRun;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The officer's Confirm: a Draft plan becomes the real schedule (machine, target start / finish, ASSIGNED) and the next N running
 * shifts of every machine are locked. Nothing else writes the real schedule from a plan.
 */
final class PlanConfirmService
{
    public function __construct(private readonly PlanLoader $loader) {}

    /** @return array{confirmed: int, locked: int, run_id: int} */
    public function confirm(int $runId, string $login, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? CarbonImmutable::now())->startOfMinute();
        $run = PrdPlanRun::findOrFail($runId);
        if ($run->status !== 'DRAFT') {
            throw new \DomainException(sprintf('Plan run %d is %s, only a Draft can be confirmed.', $runId, $run->status));
        }

        $ops = PrdWoOperation::query()->where('plan_status', 'DRAFT')->whereNotNull('draft_start')->get();
        $cal = $this->loader->calendar($now, $ops->pluck('draft_machine_id')->unique()->all());
        $shifts = max(1, (int) config('ppic.lock_shifts', 1));

        return DB::connection('tm2prd')->transaction(function () use ($ops, $cal, $shifts, $now, $run, $login) {
            $locked = 0;
            $woIds = [];
            foreach ($ops as $op) {
                /** @var PrdWoOperation $op */
                $m = (int) $op->draft_machine_id;      // 0 = crew job or subcontract step, no machine
                $lockUntil = $cal->timeAt($cal->workMinute($now, $m) + $shifts * $cal->shiftMinutes($m), $m, true);
                $isLocked = $op->draft_start->lessThan($lockUntil);
                $op->forceFill([
                    'machine_id' => $m ?: null, 'resource_type' => $m ? 'MACHINE' : 'MANUAL', 'target_start' => $op->draft_start, 'target_finish' => $op->draft_finish,
                    'plan_status' => 'CONFIRMED', 'is_locked' => $isLocked, 'lock_reason' => $isLocked ? 'Confirmed, locked' : null,
                    'draft_machine_id' => null, 'draft_start' => null, 'draft_finish' => null,
                ]);
                if ($op->task_status === 'PENDING') {
                    $op->task_status = 'ASSIGNED';
                }
                $op->save();
                $locked += $isLocked ? 1 : 0;
                $woIds[$op->wo_id] = true;
            }

            foreach (array_keys($woIds) as $woId) {
                $wo = PrdWo::find($woId);
                if ($wo && in_array($wo->wo_status, ['DRAFT', 'SIMULATED'], true)) {
                    $wo->forceFill(['wo_status' => 'SCHEDULED', 'schedule_layer' => 'COMMITTED'])->save();
                }
            }

            $run->update(['status' => 'CONFIRMED', 'confirmed_by' => $login, 'confirmed_at' => $now]);
            PrdPlanDecision::create(['run_id' => $run->run_id, 'actor' => $login, 'event' => 'CONFIRM',
                'new_value' => sprintf('%d operations, %d locked', $ops->count(), $locked), 'reason' => 'Plan confirmed']);

            return ['confirmed' => $ops->count(), 'locked' => $locked, 'run_id' => (int) $run->run_id];
        });
    }

    /** Throw the Draft away without confirming. */
    public function discard(int $runId, string $login): void
    {
        $run = PrdPlanRun::findOrFail($runId);
        PrdWoOperation::query()->where('plan_status', 'DRAFT')->update(['plan_status' => 'NONE', 'draft_machine_id' => null, 'draft_start' => null, 'draft_finish' => null, 'draft_run_id' => null]);
        $run->update(['status' => 'DISCARDED']);
        PrdPlanDecision::create(['run_id' => $run->run_id, 'actor' => $login, 'event' => 'DISCARD', 'reason' => 'Draft discarded']);
    }
}
