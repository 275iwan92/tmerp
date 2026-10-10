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
 * Runs the whole chain and leaves a DRAFT plan: enrich new Work Orders (process ids, candidate machines), refresh the gates, solve the
 * schedule, store the proposed machine / start / finish on every operation and log the run.
 * It never touches the confirmed schedule: only PlanConfirmService makes a Draft real (the officer's Confirm).
 */
final class AutoPlanRunner
{
    public function __construct(
        private readonly WoPlanningEnricher $enricher,
        private readonly GateService $gates,
        private readonly PlanLoader $loader,
    ) {}

    public function run(string $trigger = 'MANUAL', string $actor = 'ENGINE', ?CarbonImmutable $now = null): PrdPlanRun
    {
        $t0 = microtime(true);
        $now = ($now ?? CarbonImmutable::now())->startOfMinute();

        $this->prepare();
        $wos = $this->loader->wos($now);
        $cal = $this->loader->calendar($now, $this->loader->machineIds($wos));
        $solver = $this->solver();
        $result = $solver->solve($wos, $cal, $now);

        $run = DB::connection('tm2prd')->transaction(function () use ($result, $wos, $now, $trigger, $actor, $t0) {
            PrdPlanRun::where('status', 'DRAFT')->update(['status' => 'DISCARDED']);
            $run = PrdPlanRun::create([
                'trigger_kind' => $trigger, 'status' => 'DRAFT', 'plan_from' => $now, 'operations_planned' => count($result->placements),
                'exceptions_found' => count($result->exceptions), 'duration_ms' => (int) ((microtime(true) - $t0) * 1000), 'started_by' => $actor,
                'summary' => ['exceptions' => $result->exceptions, 'priority' => $result->priority, 'loads' => $result->loads, 'work_orders' => count($wos), 'metrics' => $this->metrics($result, $wos), 'baseline' => $this->baseline()],
            ]);
            $this->writeDraft($result, $run);
            PrdPlanDecision::create(['run_id' => $run->run_id, 'actor' => $actor, 'event' => 'AUTO_PLAN',
                'new_value' => sprintf('%d operations, %d exceptions', count($result->placements), count($result->exceptions)), 'reason' => $trigger]);

            return $run;
        });

        return $run;
    }

    /**
     * What a plan means in numbers, so a Draft can be compared with the plan in force before anyone confirms it.
     *
     * @return array<string, int|float>
     */
    private function metrics(PlanResult $r, array $wos = []): array
    {
        // Setup minutes saved by grouping: an operation with a die whose placement mounts no die.
        $saved = 0;
        foreach ($wos as $w) {
            foreach ($w->ops as $op) {
                $p = $r->placements[$op->id] ?? null;
                if ($p && ! empty($op->facts['die']) && ! in_array('die', array_column($p->setup['parts'] ?? [], 'kind'), true)) {
                    $saved += (int) ($op->facts['tool_min'] ?? PpicSettings::number('setup.base_minutes'));
                }
            }
        }
        $late = count(array_unique(array_column($r->exceptionsOf('LATE_RISK'), 'wo')));
        $setup = 0;
        $dies = 0;
        $washes = 0;
        foreach ($r->placements as $p) {
            $setup += $p->setupMinutes;
            foreach ($p->setup['parts'] ?? [] as $part) {
                $dies += ($part['kind'] ?? '') === 'die' ? 1 : 0;
                $washes += ($part['kind'] ?? '') === 'colour' ? 1 : 0;
            }
        }
        $moved = 0;
        // Setup and die mounts of the operations that are in the schedule in force, so the Draft can be compared with it like for like.
        $sameSetup = 0;
        $sameDies = 0;
        $ids = array_keys($r->placements);
        if ($ids) {
            foreach (PrdWoOperation::query()->whereIn('operation_id', $ids)->where('plan_status', 'CONFIRMED')->get(['operation_id', 'machine_id', 'target_start']) as $o) {
                $p = $r->placements[$o->operation_id];
                $sameSetup += $p->setupMinutes;
                $sameDies += in_array('die', array_column($p->setup['parts'] ?? [], 'kind'), true) ? 1 : 0;
                if ((int) $o->machine_id !== $p->machineId || ! $o->target_start || $o->target_start->ne($p->start)) {
                    $moved++;
                }
            }
        }
        $loads = array_column($r->loads, 'pct');

        return ['planned' => count($r->placements), 'late' => $late, 'attention' => count($r->exceptions), 'setup_minutes' => $setup, 'die_mounts' => $dies, 'washes' => $washes,
            'setup_minutes_same_ops' => $sameSetup, 'die_mounts_same_ops' => $sameDies, 'die_saved_minutes' => $saved, 'moved' => $moved, 'load_avg' => $loads ? round(array_sum($loads) / count($loads), 1) : 0.0];
    }

    /**
     * The same numbers for the schedule in force (confirmed operations that have not finished), so a Draft always has something to be compared with.
     *
     * @return array<string, int|float>
     */
    private function baseline(): array
    {
        $ops = PrdWoOperation::query()->where('plan_status', 'CONFIRMED')->whereNotIn('task_status', ['DONE', 'QC_HOLD', 'QC_PASSED', 'QC_REJECTED'])
            ->get(['wo_id', 'target_finish', 'is_locked', 'setup_base_min', 'setup_change_min', 'setup_tool_min']);
        if ($ops->isEmpty()) {
            return [];
        }
        $due = PrdWo::whereIn('wo_id', $ops->pluck('wo_id')->unique())->pluck('due_date', 'wo_id');
        $lastFinish = $ops->groupBy('wo_id')->map(fn ($g) => $g->max('target_finish'));
        $late = $lastFinish->filter(fn ($f, $id) => $f && ($due[$id] ?? null) && $f->greaterThan($due[$id]))->count();

        // Setup and die mounts only for operations the engine may still move (not locked): those are the ones a Draft can change.
        $open = $ops->where('is_locked', false);

        return ['planned' => $ops->count(), 'late' => $late, 'setup_minutes' => (int) $open->sum(fn ($o) => (int) $o->setup_base_min + (int) $o->setup_change_min + (int) $o->setup_tool_min),
            'die_mounts' => $open->where('setup_tool_min', '>', 0)->count()];
    }

    public function solver(): ScheduleSolver
    {
        return new ScheduleSolver(new SetupTimeEngine, PpicSettings::setupRules(), PpicSettings::weights(),
            PpicSettings::number('similar_window_hours'), PpicSettings::number('horizon_hours'), PpicSettings::number('group_due_hours'), PpicSettings::number('group_slack_hours'));
    }

    /** Enrich Work Orders that have no process ids yet and refresh the gates of all active ones. */
    private function prepare(): void
    {
        $wos = PrdWo::query()->whereIn('wo_status', PlanLoader::ACTIVE_WO)->get();
        foreach ($wos as $wo) {
            $needs = PrdWoOperation::where('wo_id', $wo->wo_id)->whereNull('process_id')->exists()
                || ! DB::connection('tm2prd')->table('prd_wo_operation_machine')
                    ->whereIn('operation_id', PrdWoOperation::where('wo_id', $wo->wo_id)->pluck('operation_id'))->exists();
            if ($needs) {
                $this->enricher->enrich($wo);
            }
            $this->gates->apply($wo);
        }
    }

    private function writeDraft(PlanResult $result, PrdPlanRun $run): void
    {
        // Engine drafts of earlier runs are replaced; officer moves stay as they are.
        PrdWoOperation::query()->where('plan_status', 'DRAFT')->where('planned_by', 'ENGINE')
            ->update(['plan_status' => 'NONE', 'draft_machine_id' => null, 'draft_start' => null, 'draft_finish' => null, 'draft_run_id' => null]);

        foreach ($result->placements as $p) {
            PrdWoOperation::whereKey($p->opId)->update([
                'plan_status' => 'DRAFT', 'planned_by' => 'ENGINE', 'draft_run_id' => $run->run_id,
                'draft_machine_id' => $p->machineId, 'draft_start' => $p->start, 'draft_finish' => $p->finish,
                'setup_base_min' => $p->setup['base'], 'setup_change_min' => $p->setup['change'], 'setup_tool_min' => $p->setup['tool'], 'run_minutes' => $p->runMinutes,
            ]);
        }
    }
}
