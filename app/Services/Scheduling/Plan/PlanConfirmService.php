<?php

/*
 * REMARK
 * Tujuan       : Mengonfirmasi rencana Draft ke jadwal utama, boleh hanya untuk satu WO; menjaga mesin tidak ganda.
 * Sumber hulu  : prd_plan_run (Draft), prd_wo_operation (draft_*).
 * Input        : run id, login, id WO opsional.
 * Output       : Operasi CONFIRMED (jendela beku dikunci), run CONFIRMED untuk Undo.
 * Dipakai oleh : PpicController::applyWo dan confirm.
 * Status       : Aktif di mockup.
 */

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
    public function confirm(int $runId, string $login, ?CarbonImmutable $now = null, ?int $onlyWoId = null): array
    {
        $now = ($now ?? CarbonImmutable::now())->startOfMinute();
        $run = PrdPlanRun::findOrFail($runId);
        if ($run->status !== 'DRAFT') {
            throw new \DomainException(sprintf('Plan run %d is %s, only a Draft can be confirmed.', $runId, $run->status));
        }

        $ops = PrdWoOperation::query()->where('plan_status', 'DRAFT')->whereNotNull('draft_start')
            ->when($onlyWoId, fn ($q) => $q->where('wo_id', $onlyWoId))->get();
        if ($onlyWoId && $ops->isEmpty()) {
            throw new \DomainException('This Work Order has no Draft operations to confirm.');
        }
        // Safety net: a Draft can be older than the schedule in force. Never let a confirm double-book a machine.
        foreach ($ops as $op) {
            if (! $op->draft_machine_id) {
                continue;
            }
            $clash = PrdWoOperation::query()->where('plan_status', 'CONFIRMED')->where('machine_id', $op->draft_machine_id)->where('wo_id', '!=', $op->wo_id)
                ->whereNotIn('task_status', ['DONE', 'QC_PASSED', 'CANCELLED'])->where('target_start', '<', $op->draft_finish)->where('target_finish', '>', $op->draft_start)->first();
            if ($clash) {
                throw new \DomainException(sprintf('Machine is already booked by %s from %s to %s.', PrdWo::find($clash->wo_id)?->wo_no, $clash->target_start->format('d-M H:i'), $clash->target_finish->format('d-M H:i')));
            }
        }
        $cal = $this->loader->calendar($now, $ops->pluck('draft_machine_id')->unique()->all());
        $shifts = max(1, (int) PpicSettings::number('lock_shifts'));

        return DB::connection('tm2prd')->transaction(function () use ($ops, $cal, $shifts, $now, $run, $login, $onlyWoId) {
            $locked = 0;
            $woIds = [];
            // Everything the confirm is about to overwrite, so Undo can put it back.
            $undo = [
                'ops' => $ops->map(fn ($o) => $o->only(['operation_id', 'plan_status', 'machine_id', 'resource_type', 'target_start', 'target_finish', 'is_locked', 'lock_reason', 'task_status', 'planned_by', 'draft_machine_id', 'draft_start', 'draft_finish', 'draft_run_id']))->all(),
                'wos' => PrdWo::whereIn('wo_id', $ops->pluck('wo_id')->unique())->get(['wo_id', 'wo_status', 'schedule_layer'])->map(fn ($w) => $w->only(['wo_id', 'wo_status', 'schedule_layer']))->all(),
            ];
            foreach ($ops as $op) {
                /** @var PrdWoOperation $op */
                $m = (int) $op->draft_machine_id;      // 0 = crew job or subcontract step, no machine
                $lockUntil = $cal->timeAt($cal->workMinute($now, $m) + $shifts * $cal->shiftMinutes($m), $m, true);
                // Applying one Work Order (APPLY TO LIVE SCHEDULE) fixes ALL its operations: locked whatever the freeze window says,
                // so the engine never moves them again (Jo 2026-10-13). A whole-plan confirm keeps the freeze-window rule.
                $isLocked = $onlyWoId ? true : $op->draft_start->lessThan($lockUntil);
                $op->forceFill([
                    'machine_id' => $m ?: null, 'resource_type' => $m ? 'MACHINE' : 'MANUAL', 'target_start' => $op->draft_start, 'target_finish' => $op->draft_finish,
                    'plan_status' => 'CONFIRMED', 'is_locked' => $isLocked, 'lock_reason' => $isLocked ? ($onlyWoId ? 'Applied to the live schedule' : 'Confirmed, locked') : null,
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

            if ($onlyWoId) {
                // One Work Order applied to the main schedule: it gets its own confirmed run (so Undo restores just this Work Order);
                // the Draft run stays for the rest and closes only when nothing is left in it.
                $target = PrdPlanRun::create(['trigger_kind' => 'MANUAL', 'status' => 'CONFIRMED', 'plan_from' => $now, 'operations_planned' => $ops->count(),
                    'started_by' => $login, 'confirmed_by' => $login, 'confirmed_at' => $now, 'summary' => ['undo' => $undo, 'wo_id' => $onlyWoId]]);
                if (! PrdWoOperation::query()->where('plan_status', 'DRAFT')->exists()) {
                    $run->update(['status' => 'CONFIRMED', 'confirmed_by' => $login, 'confirmed_at' => $now]);
                }
                $run = $target;
            } else {
                $run->update(['status' => 'CONFIRMED', 'confirmed_by' => $login, 'confirmed_at' => $now, 'summary' => array_merge((array) $run->summary, ['undo' => $undo])]);
            }
            PrdPlanDecision::create(['run_id' => $run->run_id, 'actor' => $login, 'event' => 'CONFIRM',
                'new_value' => sprintf('%d operations, %d locked', $ops->count(), $locked), 'reason' => 'Plan confirmed']);

            return ['confirmed' => $ops->count(), 'locked' => $locked, 'run_id' => (int) $run->run_id];
        });
    }

    /**
     * Put the schedule back as it was before the last Confirm. Refused once any of its operations has started.
     *
     * @throws \DomainException
     */
    public function undo(int $runId, string $login): void
    {
        $run = PrdPlanRun::findOrFail($runId);
        $undo = $run->summary['undo'] ?? null;
        if ($run->status !== 'CONFIRMED' || ! $undo) {
            throw new \DomainException('Only the last confirmed plan can be undone.');
        }
        if (PrdPlanRun::where('status', 'CONFIRMED')->where('run_id', '>', $run->run_id)->exists()) {
            throw new \DomainException('A later plan was confirmed; undo that one first.');
        }
        $ids = array_column($undo['ops'], 'operation_id');
        if (PrdWoOperation::whereIn('operation_id', $ids)->whereIn('task_status', ['RUNNING', 'DONE', 'QC_HOLD', 'QC_PASSED', 'QC_REJECTED'])->exists()) {
            throw new \DomainException('Work has already started on this plan; it cannot be undone.');
        }
        DB::connection('tm2prd')->transaction(function () use ($undo, $run, $login) {
            foreach ($undo['ops'] as $row) {
                $id = $row['operation_id'];
                unset($row['operation_id']);
                // The snapshot was stored as JSON (ISO strings in UTC); the table wants local date-times.
                foreach (['target_start', 'target_finish', 'draft_start', 'draft_finish'] as $k) {
                    $row[$k] = ! empty($row[$k]) ? CarbonImmutable::parse($row[$k])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s') : null;
                }
                PrdWoOperation::whereKey($id)->update($row);
            }
            foreach ($undo['wos'] as $row) {
                PrdWo::whereKey($row['wo_id'])->update(['wo_status' => $row['wo_status'], 'schedule_layer' => $row['schedule_layer']]);
            }
            $run->update(['status' => 'UNDONE']);
            PrdPlanDecision::create(['run_id' => $run->run_id, 'actor' => $login, 'event' => 'UNDO_CONFIRM', 'reason' => 'Confirm undone']);
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
