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
                'summary' => ['exceptions' => $result->exceptions, 'priority' => $result->priority, 'loads' => $result->loads, 'work_orders' => count($wos)],
            ]);
            $this->writeDraft($result, $run);
            PrdPlanDecision::create(['run_id' => $run->run_id, 'actor' => $actor, 'event' => 'AUTO_PLAN',
                'new_value' => sprintf('%d operations, %d exceptions', count($result->placements), count($result->exceptions)), 'reason' => $trigger]);

            return $run;
        });

        return $run;
    }

    public function solver(): ScheduleSolver
    {
        return new ScheduleSolver(new SetupTimeEngine, PpicSettings::setupRules(), PpicSettings::weights(),
            PpicSettings::number('similar_window_hours'), PpicSettings::number('horizon_hours'));
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
