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
 * The officer's two ways to steer the plan: give a Work Order priority (Hot job / Move up) and move one operation to another time or
 * machine (drag and drop). Both only change the Draft; the engine re-plans everything else around the change; Confirm makes it real.
 * `impact()` answers a drag before anything is saved: a dry run of the solver with the move applied, compared with the plan as it is.
 */
final class PlanChangeService
{
    public function __construct(private readonly AutoPlanRunner $runner, private readonly PlanLoader $loader) {}

    public function prioritize(int $woId, string $kind, string $reason, string $login): PrdPlanRun
    {
        $kind = $kind === 'MOVE_UP' ? 'MOVE_UP' : 'HOT';
        if (trim($reason) === '') {
            throw new \DomainException('A reason is required to give a Work Order priority.');
        }
        $wo = PrdWo::findOrFail($woId);
        $old = $wo->is_hot_job ? 'HOT' : 'NORMAL';
        $wo->forceFill(['is_hot_job' => true, 'priority' => $kind === 'HOT' ? 'HOT' : 'HIGH', 'hot_job_source' => 'OFFICER:'.$kind])->save();
        PrdWoOperation::where('wo_id', $woId)->update(['priority_note' => mb_substr($reason, 0, 120)]);
        PrdPlanDecision::create(['operation_id' => null, 'actor' => $login, 'event' => 'PRIORITY', 'old_value' => $old, 'new_value' => $kind.' '.$wo->wo_no, 'reason' => mb_substr($reason, 0, 190)]);

        return $this->runner->run('EVENT', $login);
    }

    /**
     * @return array{ok: bool, reason?: string, lines?: list<array{label: string, value: string, tone: string}>, new?: array{machine: int, start: CarbonImmutable, finish: CarbonImmutable}}
     */
    public function impact(int $opId, int $machineId, CarbonImmutable $start, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? CarbonImmutable::now())->startOfMinute();
        $op = PrdWoOperation::find($opId);
        if (! $op) {
            return ['ok' => false, 'reason' => 'Operation not found.'];
        }
        $wo = PrdWo::find($op->wo_id);
        if (in_array($op->task_status, ['RUNNING', 'DONE', 'QC_HOLD', 'QC_PASSED', 'QC_REJECTED'], true)) {
            return ['ok' => false, 'reason' => 'A running or finished operation cannot be moved.'];
        }
        if ($op->is_locked && $op->plan_status === 'CONFIRMED' && ! $wo?->is_hot_job) {
            return ['ok' => false, 'reason' => 'This task is locked. Give the Work Order priority (Hot job) with a reason to move it.'];
        }
        if ($start->lessThan($now)) {
            return ['ok' => false, 'reason' => 'The start is in the past.'];
        }

        $cand = DB::connection('tm2prd')->table('prd_wo_operation_machine')->where('operation_id', $opId)->where('machine_id', $machineId)->first();
        if (! $cand || ! $cand->eligible) {
            return ['ok' => false, 'reason' => 'This machine cannot run this process'.($cand?->reason ? ': '.$cand->reason : '.')];
        }

        $wos = $this->loader->wos($now);
        $cal = $this->loader->calendar($now, array_merge([$machineId], $this->loader->machineIds($wos)));
        $solver = $this->runner->solver();
        $before = $solver->solve($wos, $cal, $now);

        $rate = $cand->target_qty_per_hour !== null ? (float) $cand->target_qty_per_hour : null;
        $run = $rate ? (int) ceil((float) $op->qty / $rate * 60) : 60;
        $setupMin = (int) (($op->setup_base_min ?? config('ppic.setup.base_minutes', 15)) + ($op->setup_change_min ?? 0) + ($op->setup_tool_min ?? 0));
        $w = $cal->workMinute($start, $machineId);
        $newStart = $cal->timeAt($w, $machineId);
        $newFinish = $cal->timeAt($w + $setupMin + $run, $machineId, true);

        $moved = array_map(fn (PlanWo $pw) => new PlanWo($pw->id, $pw->no, $pw->due, $pw->hot, $pw->tier, $pw->ageDays, array_map(
            fn (PlanOp $po) => $po->id === $opId ? new PlanOp($po->id, $po->woId, $po->seq, $po->processName, $po->stage, $po->qtyIn, $po->candidates, $po->facts, $po->waitAfterMinutes,
                $po->minStart, $po->gate, $po->gateNote, ['machine' => $machineId, 'start' => $newStart, 'finish' => $newFinish], $po->kind, $po->leadDays) : $po, $pw->ops)), $wos);
        $after = $solver->solve($moved, $cal, $now);

        $oldStart = $this->currentStart($op);
        $oldFinish = $this->currentFinish($op);
        $lines = [
            ['label' => 'Start', 'value' => ($oldStart ? $oldStart->format('D H:i').' to ' : '').$newStart->format('D H:i'), 'tone' => 'ok'],
            ['label' => 'Finish', 'value' => ($oldFinish ? $oldFinish->format('D H:i').' to ' : '').$newFinish->format('D H:i'), 'tone' => 'ok'],
            ['label' => 'Setup / run', 'value' => $setupMin.' min / '.round($run / 60, 1).' h', 'tone' => 'ok'],
        ];
        $late = $this->newLate($before, $after);
        $shifted = $this->shiftedCount($before, $after, $opId);
        $lines[] = ['label' => 'Other operations that move', 'value' => (string) $shifted, 'tone' => $shifted > 3 ? 'warn' : 'ok'];
        $lines[] = ['label' => 'Work Orders that become late', 'value' => $late ? implode(', ', $late) : 'none', 'tone' => $late ? 'bad' : 'ok'];
        if ($op->gate_material === 'WAIT' && $op->material_ready_at && $newStart->lessThan(CarbonImmutable::parse($op->material_ready_at))) {
            return ['ok' => false, 'reason' => sprintf('Material arrives %s, after this start.', CarbonImmutable::parse($op->material_ready_at)->format('D j M H:i'))];
        }

        return ['ok' => true, 'lines' => $lines, 'new' => ['machine' => $machineId, 'start' => $newStart, 'finish' => $newFinish]];
    }

    /** Save the move as an officer placement in the Draft and let the engine re-plan the rest around it. */
    public function move(int $opId, int $machineId, CarbonImmutable $start, string $reason, string $login): PrdPlanRun
    {
        $check = $this->impact($opId, $machineId, $start);
        if (! $check['ok']) {
            throw new \DomainException($check['reason'] ?? 'The move is not allowed.');
        }
        $op = PrdWoOperation::findOrFail($opId);
        $old = $this->currentStart($op)?->format('Y-m-d H:i').' m'.($op->draft_machine_id ?? $op->machine_id);
        $op->forceFill(['plan_status' => 'DRAFT', 'planned_by' => 'OFFICER', 'draft_machine_id' => $machineId,
            'draft_start' => $check['new']['start'], 'draft_finish' => $check['new']['finish'], 'is_locked' => false, 'priority_note' => mb_substr($reason, 0, 120) ?: null])->save();
        PrdPlanDecision::create(['operation_id' => $opId, 'actor' => $login, 'event' => 'MOVE', 'old_value' => $old,
            'new_value' => $check['new']['start']->format('Y-m-d H:i').' m'.$machineId, 'reason' => mb_substr($reason, 0, 190) ?: null]);

        return $this->runner->run('EVENT', $login);
    }

    private function currentStart(PrdWoOperation $op): ?CarbonImmutable
    {
        $t = $op->plan_status === 'DRAFT' ? $op->draft_start : $op->target_start;

        return $t ? CarbonImmutable::parse($t) : null;
    }

    private function currentFinish(PrdWoOperation $op): ?CarbonImmutable
    {
        $t = $op->plan_status === 'DRAFT' ? $op->draft_finish : $op->target_finish;

        return $t ? CarbonImmutable::parse($t) : null;
    }

    /** @return list<string> WO numbers that are late after the move but were not before */
    private function newLate(PlanResult $before, PlanResult $after): array
    {
        $was = array_column($before->exceptionsOf('LATE_RISK'), 'wo');
        $out = [];
        foreach ($after->exceptionsOf('LATE_RISK') as $e) {
            if (! in_array($e['wo'], $was, true)) {
                $out[] = 'WO#'.$e['wo'];
            }
        }
        $nos = PrdWo::whereIn('wo_id', array_map(fn ($s) => (int) substr($s, 3), $out))->pluck('wo_no', 'wo_id');

        return array_map(fn ($s) => $nos[(int) substr($s, 3)] ?? $s, $out);
    }

    private function shiftedCount(PlanResult $before, PlanResult $after, int $exceptOp): int
    {
        $n = 0;
        foreach ($after->placements as $id => $p) {
            $b = $before->placements[$id] ?? null;
            if ($id !== $exceptOp && $b && ($b->start->ne($p->start) || $b->machineId !== $p->machineId)) {
                $n++;
            }
        }

        return $n;
    }
}
