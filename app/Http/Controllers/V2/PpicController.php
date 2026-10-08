<?php

declare(strict_types=1);

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\Learning\LearningEngine;
use App\Services\Scheduling\Operator\OperatorTimerService;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\GateService;
use App\Services\Scheduling\Plan\PlanChangeService;
use App\Services\Scheduling\Plan\PlanConfirmService;
use App\Services\Scheduling\Plan\PpicAccess;
use App\Services\Scheduling\Plan\WoPlanningEnricher;
use App\Support\V2\Data\Cpdel;
use App\Support\V2\Data\Ppic;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * HTTP side of the PPIC screens: a thin layer that calls the planning services and answers in the shape DevLib objects expect.
 * Rules live in App\Services\Scheduling\Plan\*.
 */
final class PpicController extends Controller
{
    private function login(): string
    {
        return (string) (Auth::user()->login ?? 'unknown');
    }

    /** Changing the plan needs the PPIC_SCHEDULING flag (administrators hold it); everyone with Production access reads. */
    private function schedulerOnly(): void
    {
        abort_unless(PpicAccess::canSchedule(Auth::user()), 403, t('You need the PPIC Scheduling access flag to change the plan.'));
    }

    private function back(string $slug = 'production-scheduling'): string
    {
        return route('v2.screen', $slug);
    }

    /** Run the engines now: a new Draft. */
    public function run(AutoPlanRunner $runner): RedirectResponse
    {
        $this->schedulerOnly();
        $run = $runner->run('MANUAL', $this->login());
        Ppic::forget();

        return redirect($this->back())->with('v2_flash', tf('Draft plan :id ready: :n operations planned, :e need attention.', ['id' => $run->run_id, 'n' => $run->operations_planned, 'e' => $run->exceptions_found]));
    }

    public function confirm(Request $r, PlanConfirmService $service): RedirectResponse
    {
        $this->schedulerOnly();
        $d = $r->validate(['run' => ['required', 'integer']]);
        try {
            $res = $service->confirm((int) $d['run'], $this->login());
        } catch (\DomainException $e) {
            return redirect($this->back())->withErrors(['plan' => $e->getMessage()]);
        }
        Ppic::forget();

        return redirect($this->back())->with('v2_flash', tf('Plan confirmed: :n operations scheduled, :l locked.', ['n' => $res['confirmed'], 'l' => $res['locked']]));
    }

    public function discard(Request $r, PlanConfirmService $service): RedirectResponse
    {
        $this->schedulerOnly();
        $d = $r->validate(['run' => ['required', 'integer']]);
        $service->discard((int) $d['run'], $this->login());
        Ppic::forget();

        return redirect($this->back())->with('v2_flash', t('Draft discarded.'));
    }

    public function prioritize(Request $r, PlanChangeService $changes): RedirectResponse
    {
        $this->schedulerOnly();
        $d = $r->validate(['wo_id' => ['required', 'integer'], 'kind' => ['required', 'in:HOT,MOVE_UP'], 'reason' => ['required', 'string', 'max:190']], [], ['reason' => t('Reason')]);
        $run = $changes->prioritize((int) $d['wo_id'], $d['kind'], $d['reason'], $this->login());
        Ppic::forget();

        return redirect($this->back())->with('v2_flash', tf('Priority saved. New Draft plan :id.', ['id' => $run->run_id]));
    }

    /** Gantt: judge a drag before it is saved. */
    public function impact(Request $r, PlanChangeService $changes): JsonResponse
    {
        [$op, $machine, $start] = $this->move($r);
        $res = $changes->impact($op, $machine, $start);
        if (! $res['ok']) {
            return response()->json(['ok' => false, 'reason' => $res['reason'] ?? t('The move is not allowed.')]);
        }

        return response()->json(['ok' => true, 'lines' => $res['lines'], 'row' => 'm'.$res['new']['machine'],
            'start' => Ppic::hm(Ppic::hours($res['new']['start'])), 'end' => Ppic::hm(Ppic::hours($res['new']['finish']))]);
    }

    /** Gantt: save the move into the Draft and re-plan around it. */
    public function moveOp(Request $r, PlanChangeService $changes): JsonResponse
    {
        $this->schedulerOnly();
        [$op, $machine, $start] = $this->move($r);
        try {
            $run = $changes->move($op, $machine, $start, 'Moved on the Gantt', $this->login());
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'reason' => $e->getMessage()]);
        }
        Ppic::forget();

        return response()->json(['ok' => true, 'draft' => 'DRAFT '.$run->run_id, 'reload' => true]);
    }

    /** @return array{0: int, 1: int, 2: CarbonImmutable} */
    private function move(Request $r): array
    {
        $d = $r->validate(['task' => ['required', 'integer'], 'row' => ['required', 'string'], 'start' => ['required', 'string']]);
        [$h, $m] = array_pad(explode(':', $d['start']), 2, 0);

        return [(int) $d['task'], (int) ltrim($d['row'], 'm'), Ppic::at((int) $h + (int) $m / 60)];
    }

    /** Kanban: the engine owns the schedule, so a drag between columns is refused with the way to do it right. */
    public function kanbanMove(): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => t('Scheduling is done by the engine. Drag the bar on the Gantt, or give the Work Order priority.')], 422);
    }

    /** Spreadsheet: target per hour, waste and spoilage typed by the officer override the engine estimate. */
    public function sheetSave(Request $r): JsonResponse
    {
        $this->schedulerOnly();
        $changed = (array) $r->input('changed', []);
        $n = 0;
        foreach ($changed as $row) {
            $op = PrdWoOperation::find((int) ($row['id'] ?? 0));
            if (! $op) {
                continue;
            }
            $op->forceFill([
                'rate_band_value' => isset($row['target']) && $row['target'] !== '' ? (float) $row['target'] : $op->rate_band_value,
                'waste_qty' => isset($row['waste']) && $row['waste'] !== '' ? (float) $row['waste'] : $op->waste_qty,
                'tolerance_qty' => isset($row['spoilage']) && $row['spoilage'] !== '' ? (float) $row['spoilage'] : $op->tolerance_qty,
                'updated_by' => $this->login(),
            ])->save();
            $n++;
        }
        Ppic::forget();

        return response()->json(['rows' => Ppic::sheetRows(Ppic::operations()), 'saved' => $n]);
    }

    /**
     * Create Work Order from delivery plan lines: ProDev data -> Work Order, then enrich (machines, targets, setup), gates, and one Draft plan.
     * "all" = every pending line; otherwise the single line in `delivery`.
     */
    public function createWo(Request $r, WoPlanningEnricher $enricher, GateService $gates, AutoPlanRunner $runner): RedirectResponse
    {
        $this->schedulerOnly();
        $d = $r->validate(['delivery' => ['nullable', 'integer'], 'all' => ['nullable', 'boolean']]);
        $ids = ! empty($d['all']) ? array_column(Ppic::pendingDeliveries(500), 'id') : array_filter([(int) ($d['delivery'] ?? 0)]);
        $made = 0;
        $errors = [];
        foreach ($ids as $id) {
            // Same door as Post to Production on Customer Plan Delivery: creates the Work Order(s) and marks the line Posted.
            if ($error = Cpdel::post((int) $id)) {
                $errors[] = $error;

                continue;
            }
            foreach (PrdWo::where('source_delivery_id', $id)->get() as $wo) {
                $enricher->enrich($wo);
                $gates->apply($wo);
                $made++;
            }
        }
        $back = redirect(route('v2.screen', 'production-wo'));
        if ($made === 0) {
            return $back->withErrors(['create' => $errors ? implode(' ', $errors) : t('No Work Order was created.')]);
        }
        $run = $runner->run('EVENT', $this->login());
        Ppic::forget();

        return $back->with('v2_flash', tf(':n Work Order(s) created. Draft plan :id is ready on Production Scheduling for you to Confirm.', ['n' => $made, 'id' => $run->run_id]))
            ->withErrors($errors ? ['create' => implode(' ', $errors)] : []);
    }

    /** Work Order page: read ProDev data again and refresh gates. */
    public function refreshWo(int $wo, WoPlanningEnricher $enricher, GateService $gates): RedirectResponse
    {
        $this->schedulerOnly();
        $model = PrdWo::findOrFail($wo);
        $res = $enricher->enrich($model);
        $gates->apply($model);
        Ppic::forget();

        return redirect(route('v2.screen', 'production-wo').'?id='.$wo)->with('v2_flash', tf('Work Order refreshed from Product Development: :n operations, :m without a machine.', ['n' => $res['operations'], 'm' => $res['without_machine']]));
    }

    /** Work Order page: record good output for an operation (the same table the shop floor uses). */
    public function output(Request $r, int $wo): RedirectResponse
    {
        $d = $r->validate(['operation_id' => ['required', 'integer'], 'qty_good' => ['required', 'numeric', 'min:0'], 'note' => ['nullable', 'string', 'max:190']], [], ['qty_good' => t('Good quantity')]);
        $op = PrdWoOperation::where('wo_id', $wo)->where('operation_id', $d['operation_id'])->firstOrFail();
        DB::connection('tm2prd')->table('prd_wo_operation_output')->insert([
            'operation_id' => $op->operation_id, 'qty_good' => $d['qty_good'], 'notes' => $d['note'] ?? null, 'recorded_by' => $this->login(), 'recorded_at' => now(), 'created_at' => now(),
        ]);
        if ($op->task_status === 'ASSIGNED' || $op->task_status === 'PENDING') {
            $op->forceFill(['actual_start' => $op->actual_start ?? now()]);
            if ($op->canTransitionTo('RUNNING')) {
                $op->task_status = 'RUNNING';
            }
            $op->save();
        }
        Ppic::forget();

        return redirect(route('v2.screen', 'production-wo').'?id='.$wo)->with('v2_flash', t('Output recorded.'));
    }

    // ------------------------------------------------------------------ operator screen (tablet at the machine)

    private function operatorBack(int $machine, ?string $flash = null): RedirectResponse
    {
        $r = redirect(route('v2.screen', 'production-operator').'?machine='.$machine);

        return $flash ? $r->with('v2_flash', $flash) : $r;
    }

    private function operatorOp(int $op): PrdWoOperation
    {
        return PrdWoOperation::findOrFail($op);
    }

    public function opPlay(int $op, OperatorTimerService $timer): RedirectResponse
    {
        $o = $this->operatorOp($op);
        try {
            $kind = $timer->play($op, $this->login());
        } catch (\DomainException $e) {
            return $this->operatorBack((int) $o->machine_id)->withErrors(['timer' => $e->getMessage()]);
        }

        return $this->operatorBack((int) $o->machine_id, $kind === 'SETUP' ? t('Setup timer started.') : t('Run timer started.'));
    }

    public function opPause(Request $r, int $op, OperatorTimerService $timer): RedirectResponse
    {
        $o = $this->operatorOp($op);
        $d = $r->validate(['reason' => ['required', 'string']], [], ['reason' => t('Reason')]);
        try {
            $timer->pause($op, $d['reason'], $this->login());
        } catch (\DomainException $e) {
            return $this->operatorBack((int) $o->machine_id)->withErrors(['timer' => $e->getMessage()]);
        }
        // Machine problem: go straight to a Breakdown Maintenance request filled with this machine and Work Order.
        if ($d['reason'] === 'MACHINE') {
            return redirect($this->problemUrl($o));
        }

        return $this->operatorBack((int) $o->machine_id, t('Timer paused.'));
    }

    public function opStop(Request $r, int $op, OperatorTimerService $timer): RedirectResponse
    {
        $o = $this->operatorOp($op);
        $d = $r->validate(['good' => ['required', 'numeric', 'min:0'], 'waste' => ['nullable', 'numeric', 'min:0'], 'reject' => ['nullable', 'numeric', 'min:0'],
            'scrap_type_id' => ['nullable', 'integer'], 'change_kind' => ['nullable', 'string']], [], ['good' => t('Good quantity')]);
        $sum = $timer->stop($op, $d, $this->login());
        Ppic::forget();

        return $this->operatorBack((int) $o->machine_id, tf('Job stopped: setup :s min, run :r min, pause :p min.', ['s' => $sum['setup'], 'r' => $sum['run'], 'p' => $sum['pause']]));
    }

    public function opQuantity(Request $r, int $op, OperatorTimerService $timer): RedirectResponse
    {
        $o = $this->operatorOp($op);
        $d = $r->validate(['qty' => ['required', 'numeric', 'min:0.01']], [], ['qty' => t('Quantity')]);
        $timer->addQuantity($op, (float) $d['qty'], $this->login());
        Ppic::forget();

        return $this->operatorBack((int) $o->machine_id, t('Quantity recorded.'));
    }

    /** Problem button: the Maintenance Request form with machine, Work Order and symptom filled in. */
    public function opProblem(int $op): RedirectResponse
    {
        return redirect($this->problemUrl($this->operatorOp($op)));
    }

    private function problemUrl(PrdWoOperation $op): string
    {
        $asset = DB::connection('tm2mnt')->table('mnt_asset')->where('machine_id', $op->machine_id)->value('id_asset');
        $wo = (string) DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $op->wo_id)->value('wo_no');

        return route('v2.screen', 'maintenance-request').'?'.http_build_query(['new' => 1, 'asset_id' => $asset, 'asset_stopped' => 1,
            'symptom' => 'Reported from the operator screen while running '.$wo.' ('.$op->process_name.').']);
    }

    // ------------------------------------------------------------------ learning changes

    public function learnRun(LearningEngine $engine): RedirectResponse
    {
        $this->schedulerOnly();
        $applied = $engine->run($this->login());

        return redirect(route('v2.screen', 'production-learning'))->with('v2_flash', tf(':n value(s) adjusted from finished jobs.', ['n' => count($applied)]));
    }

    public function learnRevert(Request $r, LearningEngine $engine): RedirectResponse
    {
        $this->schedulerOnly();
        $d = $r->validate(['decision' => ['required', 'integer']], [], ['decision' => t('Change')]);
        try {
            $engine->revert((int) $d['decision'], $this->login());
        } catch (\DomainException $e) {
            return redirect(route('v2.screen', 'production-learning'))->withErrors(['learn' => $e->getMessage()]);
        }

        return redirect(route('v2.screen', 'production-learning'))->with('v2_flash', t('Value put back and frozen.'));
    }
}
