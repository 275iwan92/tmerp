<?php

declare(strict_types=1);

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\GateService;
use App\Services\Scheduling\Plan\PlanChangeService;
use App\Services\Scheduling\Plan\PlanConfirmService;
use App\Services\Scheduling\Plan\WoPlanningEnricher;
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

    private function back(string $slug = 'production-scheduling'): string
    {
        return route('v2.screen', $slug);
    }

    /** Run the engines now: a new Draft. */
    public function run(AutoPlanRunner $runner): RedirectResponse
    {
        $run = $runner->run('MANUAL', $this->login());
        Ppic::forget();

        return redirect($this->back())->with('v2_flash', tf('Draft plan :id ready: :n operations planned, :e need attention.', ['id' => $run->run_id, 'n' => $run->operations_planned, 'e' => $run->exceptions_found]));
    }

    public function confirm(Request $r, PlanConfirmService $service): RedirectResponse
    {
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
        $d = $r->validate(['run' => ['required', 'integer']]);
        $service->discard((int) $d['run'], $this->login());
        Ppic::forget();

        return redirect($this->back())->with('v2_flash', t('Draft discarded.'));
    }

    public function prioritize(Request $r, PlanChangeService $changes): RedirectResponse
    {
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

    /** Work Order page: read ProDev data again and refresh gates. */
    public function refreshWo(int $wo, WoPlanningEnricher $enricher, GateService $gates): RedirectResponse
    {
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
}
