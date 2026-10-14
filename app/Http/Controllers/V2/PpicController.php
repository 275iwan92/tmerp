<?php

/*
 * REMARK
 * Tujuan       : Aksi layar PPIC: buat WO dari baris kirim, PROCESS, APPLY (satu WO) dan APPLY ALL, seret tertunda/Discard, hapus, ubah target/urutan/bahan, tambah/hapus baris BOP dan BOM, RESET sheet ke output engine (resetOps/resetMaterials), isi proses baris BOM, prioritas, gerak Gantt, timer operator.
 * Sumber hulu  : Ppic (app/Support/V2/Data/Ppic.php) + Plan* services (AutoPlanRunner, PlanConfirmService, PlanChangeService, GateService) + WoGenerationService.
 * Input        : Request POST/JSON dari layar production-wo, ppic-color-sequence, production-scheduling, production-operator.
 * Output       : Redirect + flash, atau JSON {rows, saved} / {error}. Menulis prd_wo (termasuk earliest_start dan pending_header), prd_wo_operation, prd_wo_material, prd_plan_decision, hasil + log timer operator; keputusan SHEET_RESET, MATERIAL_PROCESS. Tombol lama Reset to ProDev order dan Reset to engine quantities sudah dihapus (digantikan RESET).
 * Dipakai oleh : routes/v2_ppic.php (nama rute v2.ppic.*), resources/views/v2/screens/production-wo.blade.php.
 * Status       : Aktif di mockup (database ddt*). Engine penjadwalan tetap sumber keputusan; controller hanya meneruskan. Pengaman jadwal: PROCESS diblok dan APPLY / APPLY ALL ditolak bila ada proses tak terjadwal (ScheduleGuard).
 */

declare(strict_types=1);

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Master\ProdProcess;
use App\Models\Prd\PrdPlanDecision;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoMaterial;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sdi\CoOrderDelivery;
use App\Services\Scheduling\Learning\LearningEngine;
use App\Services\Scheduling\Operator\OperatorTimerService;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\GateService;
use App\Services\Scheduling\Plan\ScheduleGuard;
use App\Services\Scheduling\Plan\PlanChangeService;
use App\Services\Scheduling\Plan\PlanConfirmService;
use App\Services\Scheduling\Sequence\SequenceSuggester;
use App\Services\Scheduling\Sequence\TraceRecorder;
use App\Services\Scheduling\Plan\PlanEvents;
use App\Services\Scheduling\Plan\PpicAccess;
use App\Services\Scheduling\Plan\SheetReset;
use App\Services\Scheduling\Plan\WoPlanningEnricher;
use App\Services\Scheduling\WoGenerationService;
use App\Support\V2\Data\Cpdel;
use App\Support\V2\Data\Ppic;
use Carbon\CarbonImmutable;
use Devlib\Locale;
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
        $d = $r->validate(['wo_id' => ['required', 'integer'], 'kind' => ['required', 'in:HOT,MOVE_UP,NORMAL'], 'reason' => ['required', 'string', 'max:190']], [], ['reason' => t('Reason')]);
        $to = $r->boolean('wo_form') ? route('v2.screen', 'production-wo').'?id='.(int) $d['wo_id'] : $this->back();
        try {
            // On the Work Order form the priority is a pending change that PROCESS applies; the Workspace applies it at once.
            if ($r->boolean('wo_form')) {
                $changes->queuePriority((int) $d['wo_id'], $d['kind'], $d['reason'], $this->login());
                Ppic::forget();

                return redirect($to)->with('v2_flash', t('Priority is a pending change. PROCESS applies it.'));
            }
            $run = $d['kind'] === 'NORMAL'
                ? $changes->undoPriority((int) $d['wo_id'], $this->login())
                : $changes->prioritize((int) $d['wo_id'], $d['kind'], $d['reason'], $this->login());
        } catch (\DomainException $e) {
            return redirect($to)->withErrors(['plan' => $e->getMessage()]);
        }
        Ppic::forget();

        return redirect($to)->with('v2_flash', tf('Priority saved. New Draft plan :id.', ['id' => $run->run_id]));
    }

    /** Gantt: judge a drag before it is saved. */
    public function impact(Request $r, PlanChangeService $changes): JsonResponse
    {
        [$op, $machine, $start] = $this->move($r);
        $res = $changes->impact($op, $machine, $start);
        if (! $res['ok']) {
            return response()->json(['ok' => false, 'reason' => $res['reason'] ?? t('The move is not allowed.')]);
        }

        $dt = fn ($t) => Locale::dt($t);

        return response()->json(['ok' => true, 'no_change' => (bool) ($res['no_change'] ?? false), 'lines' => $res['lines'], 'moves' => $this->moveRows($res['moves'] ?? []), 'row' => 'm'.$res['new']['machine'],
            'start' => Ppic::hm(Ppic::hours($res['new']['start'])), 'end' => Ppic::hm(Ppic::hours($res['new']['finish'])),
            'sheet' => ['start' => $res['new']['start']->format('Y-m-d H:i:s'), 'finish' => $res['new']['finish']->format('Y-m-d H:i:s'), 'machine' => $this->machineName($res['new']['machine'])]]);
    }

    /** Machine name for the BOP sheet while a move is previewed (no machine = SUBCON). */
    private function machineName(?int $id): string
    {
        $m = $id ? DB::connection('tm2mst')->table('prod_machine')->where('machine_id', $id)->first(['machine_code', 'machine_name']) : null;

        return $m ? $m->machine_code.' · '.$m->machine_name : 'SUBCON';
    }

    /** The operations an impact preview moves, in Gantt terms (row key, start, end as hours text) so the board can draw them in their new place. */
    private function moveRows(array $moves): array
    {
        // a step without a machine sits on its subcontract row 'p:<PROCESS>' (manual jobs are not drawn, the board ignores them)
        $row = fn ($m, $process) => $m ? 'm'.$m : 'p:'.strtoupper((string) $process);
        $hm = fn ($t) => $t ? Ppic::hm(Ppic::hours($t)) : null;

        return array_map(fn ($m) => ['id' => $m['op'], 'wo' => $m['wo'], 'process' => $m['process'],
            'row' => $row($m['to']['machine'], $m['process']), 'start' => $hm($m['to']['start']), 'end' => $hm($m['to']['finish']),
            'sheet' => ['start' => $m['to']['start']->format('Y-m-d H:i:s'), 'finish' => $m['to']['finish']->format('Y-m-d H:i:s'), 'machine' => $this->machineName($m['to']['machine'])],
            'from' => ['row' => $row($m['from']['machine'], $m['process']), 'start' => $hm($m['from']['start']), 'end' => $hm($m['from']['finish'])]], $moves);
    }

    /** Gantt: save the move into the Draft and re-plan around it. */
    public function moveOp(Request $r, PlanChangeService $changes): JsonResponse
    {
        $this->schedulerOnly();
        [$op, $machine, $start] = $this->move($r);
        try {
            $run = $changes->move($op, $machine, $start, (string) $r->input('reason', ''), $this->login());
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'reason' => $e->getMessage()]);
        }
        Ppic::forget();

        return response()->json(['ok' => true, 'draft' => 'DRAFT '.$run->run_id, 'reload' => true]);
    }

    /** Put the schedule back as it was before the last Confirm. */
    public function undoConfirm(PlanConfirmService $service): RedirectResponse
    {
        $this->schedulerOnly();
        $run = Ppic::lastConfirmedRun();
        try {
            abort_unless($run, 404);
            $service->undo((int) $run->run_id, $this->login());
        } catch (\DomainException $e) {
            return redirect($this->back())->withErrors(['plan' => $e->getMessage()]);
        }
        Ppic::forget();

        return redirect($this->back())->with('v2_flash', tf('Plan :id undone: the schedule is as it was before it was confirmed.', ['id' => $run->run_id]));
    }

    /** Take a Work Order out of die grouping, or put it back. */
    public function dieGroup(Request $r, PlanChangeService $changes): RedirectResponse
    {
        $this->schedulerOnly();
        $d = $r->validate(['wo_id' => ['required', 'integer'], 'mode' => ['required', 'in:OUT,IN'], 'reason' => ['required', 'string', 'max:190']], [], ['reason' => t('Reason')]);
        $run = $changes->setGroupOut((int) $d['wo_id'], $d['mode'] === 'OUT', $d['reason'], $this->login());
        Ppic::forget();

        return redirect($this->back())->with('v2_flash', tf('Die grouping saved. New Draft plan :id.', ['id' => $run->run_id]));
    }

    /** Take an officer placement away (the Overrides list): the engine plans the operation again. */
    public function unpin(Request $r, PlanChangeService $changes): RedirectResponse
    {
        $this->schedulerOnly();
        $d = $r->validate(['op' => ['required', 'integer']]);
        try {
            $run = $changes->unpin((int) $d['op'], $this->login());
        } catch (\DomainException $e) {
            return redirect($this->back())->withErrors(['plan' => $e->getMessage()]);
        }
        Ppic::forget();

        return redirect($this->back())->with('v2_flash', tf('Placement released. New Draft plan :id.', ['id' => $run->run_id]));
    }

    /** Colour Sequence: the officer runs a job before another one on the same press (a reason is required; the result is a Draft). */
    public function colorMove(Request $r, PlanChangeService $changes): RedirectResponse
    {
        $this->schedulerOnly();
        $d = $r->validate(['op' => ['required', 'integer'], 'before' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:170']], [], ['reason' => t('Reason')]);
        try {
            $run = $changes->moveBefore((int) $d['op'], (int) $d['before'], $d['reason'], $this->login());
        } catch (\DomainException $e) {
            return redirect($this->back('ppic-color-sequence'))->withErrors(['plan' => t($e->getMessage())]);
        }
        Ppic::forget();

        return redirect($this->back('ppic-color-sequence'))->with('v2_flash', tf('Colour order saved in Draft plan :id. Confirm it on the PPIC Workspace.', ['id' => $run->run_id]));
    }

    /** Colour Sequence: an operator asks for another order; the officer answers on the Workspace. Nothing in the plan changes by itself. */
    public function colorRequest(Request $r): RedirectResponse
    {
        $d = $r->validate(['op' => ['required', 'integer'], 'before' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:170']], [], ['reason' => t('Reason')]);
        PrdPlanDecision::create(['operation_id' => (int) $d['op'], 'actor' => $this->login(), 'event' => 'COLOR_REQUEST', 'new_value' => (string) (int) $d['before'], 'reason' => $d['reason']]);

        return redirect($this->back('ppic-color-sequence'))->with('v2_flash', t('Request sent to the PPIC officer.'));
    }

    /** The officer accepts (moves the job into the Draft) or rejects an operator request. */
    public function colorAnswer(Request $r, PlanChangeService $changes): RedirectResponse
    {
        $this->schedulerOnly();
        $d = $r->validate(['request' => ['required', 'integer'], 'answer' => ['required', 'in:ACCEPT,REJECT'], 'reason' => ['required', 'string', 'max:170']], [], ['reason' => t('Reason')]);
        $req = PrdPlanDecision::where('decision_id', (int) $d['request'])->where('event', 'COLOR_REQUEST')->firstOrFail();
        try {
            if ($d['answer'] === 'ACCEPT') {
                $changes->moveBefore((int) $req->operation_id, (int) $req->new_value, $req->actor.': '.$req->reason, $this->login());
            }
        } catch (\DomainException $e) {
            return redirect($this->back())->withErrors(['plan' => t($e->getMessage())]);
        }
        PrdPlanDecision::create(['operation_id' => $req->operation_id, 'actor' => $this->login(), 'event' => 'COLOR_'.$d['answer'], 'old_value' => (string) $req->decision_id, 'reason' => $d['reason']]);
        Ppic::forget();

        return redirect($this->back())->with('v2_flash', $d['answer'] === 'ACCEPT' ? t('Request accepted: the job is moved in the Draft. Confirm it to apply.') : t('Request rejected.'));
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
        $d = $r->validate(['delivery' => ['nullable', 'array'], 'delivery.*' => ['integer'], 'all' => ['nullable', 'boolean']]);
        $ids = ! empty($d['all']) ? array_column(Ppic::pendingDeliveries(500), 'id') : array_values(array_filter(array_map('intval', (array) ($d['delivery'] ?? []))));
        if ($ids === []) {
            return redirect(route('v2.screen', 'production-wo').'?new=1')->withErrors(['create' => t('Tick at least one line to process.')]);
        }
        $made = 0;
        $created = [];
        $errors = [];
        foreach ($ids as $id) {
            // Same door as Post to Production on Customer Plan Delivery: creates the Work Order(s) and marks the line Posted.
            if ($error = Cpdel::post((int) $id)) {
                $errors[] = $error;

                continue;
            }
            foreach (PrdWo::where('source_delivery_id', $id)->where('wo_status', '!=', 'CANCELLED')->get() as $wo) {
                $enricher->enrich($wo);
                $gates->apply($wo);
                $made++;
                $created[] = (int) $wo->wo_id;
            }
        }
        if ($made === 0) {
            return redirect(route('v2.screen', 'production-wo').'?new=1')->withErrors(['create' => $errors ? implode(' ', $errors) : t('No Work Order was created.')]);
        }
        // The form opens on the first Work Order; the others wait in the queue ("Next Work Order" on the form).
        session()->put('wo_queue', array_slice($created, 1));
        $back = redirect(route('v2.screen', 'production-wo').'?id='.$created[0]);
        // Cpdel::post already asked the engines for a Draft (PlanEvents); read it instead of planning again.
        $run = Ppic::draftRun();
        Ppic::forget();

        return $back->with('v2_flash', tf(':n Work Order(s) created. Check the form, PROCESS it, then APPLY TO LIVE SCHEDULE in Simulation.', ['n' => $made]))
            ->withErrors($errors ? ['create' => implode(' ', $errors)] : []);
    }

    /**
     * APPLY TO LIVE SCHEDULE (the only way to confirm; the header Confirm WO button was removed): applies this Work Order, all of its
     * operations, to the live schedule. Engines and gates apply; the Work Order is locked as a whole afterwards.
     */
    public function applyWo(int $wo, PlanConfirmService $service, AutoPlanRunner $runner): RedirectResponse
    {
        $this->schedulerOnly();
        $form = route('v2.screen', 'production-wo').'?id='.$wo.'&open=sim';
        $r = $this->applyOne($wo, $service, $runner);
        if (! $r['ok']) {
            return redirect($form)->withErrors(['apply' => $r['message']]);
        }

        return redirect($form)->with('v2_flash', $r['message']);
    }

    /** @return array{ok: bool, message: string} */
    private function applyOne(int $wo, PlanConfirmService $service, AutoPlanRunner $runner): array
    {
        $model = PrdWo::find($wo);
        if (! $model) {
            return ['ok' => false, 'message' => t('Work Order not found.')];
        }
        if (! in_array($model->wo_status, ['DRAFT', 'SIMULATED'], true)) {
            return ['ok' => false, 'message' => t('Applied WO is locked; Undo Confirm first.')];
        }
        if ($why = Ppic::applyBlockedReason($wo)) {
            return ['ok' => false, 'message' => $why];
        }
        // The engines run again against the schedule in force NOW (another Work Order may have been applied since this one was drafted).
        $runner->run('MANUAL', $this->login());
        Ppic::forget();
        $d = Ppic::workOrder($wo);
        if (! $d) {
            return ['ok' => false, 'message' => t('Work Order not found.')];
        }
        if ($unscheduled = ScheduleGuard::issues($wo)) {
            return ['ok' => false, 'message' => t('Rejected').': '.ScheduleGuard::line($unscheduled)];
        }
        $check = Ppic::engineCheck($d['ops']);
        if (! $check['ok']) {
            return ['ok' => false, 'message' => t('Rejected').': '.$check['reason']];
        }
        try {
            $res = $service->confirm((int) Ppic::draftRun()->run_id, $this->login(), null, $wo);
        } catch (\DomainException $e) {
            return ['ok' => false, 'message' => t($e->getMessage())];
        }
        Ppic::forget();

        return ['ok' => true, 'message' => tf('Work Order applied to the live schedule: :n operations are fixed.', ['n' => $res['confirmed']])];
    }

    /** Job order learning, USE: the suggested order goes onto the machine as pending moves (same path as a drag), still DRAFT; PROCESS and APPLY follow as usual. */
    public function sequenceUse(int $wo, int $machine, PlanChangeService $changes): RedirectResponse
    {
        $this->schedulerOnly();
        $res = SequenceSuggester::use($machine, $changes, $this->login());

        return redirect(route('v2.screen', 'production-wo').'?id='.$wo.'&open=sim')
            ->with('v2_flash', tf('Suggested order taken: :n jobs moved as pending changes (still Draft). Press PROCESS to plan them.', ['n' => $res['moved']]));
    }

    /** Job order learning, DISMISS: logged as SEQ_REJECT; the strip stays hidden until the arrangement changes. */
    public function sequenceDismiss(int $wo, int $machine): RedirectResponse
    {
        $this->schedulerOnly();
        SequenceSuggester::dismiss($machine, $this->login());

        return redirect(route('v2.screen', 'production-wo').'?id='.$wo.'&open=sim');
    }

    /** APPLY ALL WOs: every Work Order that has a Draft operation on the chosen process and machine is applied (each through applyOne). */
    public function applyAll(Request $r, PlanConfirmService $service, AutoPlanRunner $runner): JsonResponse
    {
        $this->schedulerOnly();
        $process = (string) $r->input('process', '');
        $machine = (string) $r->input('machine', '');
        if (PrdWoOperation::where('pending_move', true)->exists()) {
            return response()->json(['ok' => false, 'error' => t('Press PROCESS first: there are pending changes.')], 422);
        }
        $results = [];
        foreach (Ppic::applyCandidates($process, $machine) as $c) {
            $one = $this->applyOne($c['wo_id'], $service, $runner);
            $results[] = ['wo' => $c['wo_no'], 'ok' => $one['ok'], 'message' => $one['message']];
        }

        return response()->json(['ok' => true, 'results' => $results, 'applied' => count(array_filter($results, fn ($x) => $x['ok']))]);
    }

    /**
     * PROCESS: runs the whole chain for this Work Order, from the source to the schedule, once (also for every pending drag):
     * (1) material lines again from Inquiry/ProDev (officer's Required / Release / Purchase edits kept) = WoGenerationService::regenerateBom,
     * (2) target / waste / setup / candidates per operation = WoPlanningEnricher::enrich,
     * (3) material / tooling / artwork gates = GateService::apply,
     * (4) the schedule into the Draft = AutoPlanRunner::run (officer placements respected, CONFIRMED operations untouched).
     */
    public function processWo(int $wo, WoPlanningEnricher $enricher, GateService $gates, AutoPlanRunner $runner, PlanChangeService $changes, WoGenerationService $gen): RedirectResponse
    {
        $this->schedulerOnly();
        $form = route('v2.screen', 'production-wo').'?id='.$wo.'&open=sim';
        $model = PrdWo::findOrFail($wo);
        if (! in_array($model->wo_status, ['DRAFT', 'SIMULATED'], true)) {
            return redirect($form)->withErrors(['process' => t('Applied WO is locked; Undo Confirm first.')]);
        }
        // (0) pending BOP order and priority become real; (1) ENGINE BOP: the operations follow the BOP of ProDev; (2) ENGINE BOM: the material lines (officer edits and substitutes kept)
        $pins = TraceRecorder::capturePins();   // engine placement of dragged jobs, before PROCESS clears the pending moves (job order learning)
        $changes->applyPendingHeader($model, $this->login());
        $bop = $gen->rebuildOperations($model);
        try {
            $gen->regenerateBom($model);
        } catch (\Throwable $e) {
            // a Work Order without an Inquiry part keeps its lines as they are
        }
        $enricher->enrich($model);
        $gates->apply($model->fresh());
        $runner->run('MANUAL', $this->login());
        $changes->clearPending();
        Ppic::forget();
        TraceRecorder::recordTrial($wo, $pins);
        // Schedule guard (Jo 2026-10-14): every process must sit on a machine (or SUBCON / crew). Otherwise PROCESS is blocked: the hash
        // is not stored, so APPLY stays off, and the popup lists the work order, process and reason.
        if ($unscheduled = ScheduleGuard::issues($wo)) {
            return redirect($form)
                ->withErrors(['process' => tf('PROCESS blocked: :n process(es) are not scheduled. :lines', ['n' => count($unscheduled), 'lines' => ScheduleGuard::line($unscheduled)])]);
        }
        $model->forceFill(['process_hash' => Ppic::fingerprint($wo), 'processed_at' => now()])->save();
        Ppic::forget();

        $ops = array_values(array_filter(Ppic::operations(), fn ($o) => $o['wo_id'] === $wo));
        $starts = array_filter(array_column($ops, 'start'));
        $ends = array_filter(array_column($ops, 'finish'));
        $placed = count(array_filter($ops, fn ($o) => $o['start']));
        $late = count(array_filter($ops, fn ($o) => $o['due'] && $o['finish'] && $o['finish']->greaterThan($o['due'])));
        $clash = (int) (Ppic::simulation($wo)['clashes'] ?? 0);
        $dt = fn ($d) => $d ? Locale::dt($d) : '—';
        $line = tf(':n of :t operations scheduled · :a → :b · :c clash · :l late', ['n' => $placed, 't' => count($ops),
            'a' => $dt($starts ? min($starts) : null), 'b' => $dt($ends ? max($ends) : null), 'c' => $clash, 'l' => $late])
            .(array_sum([$bop['created'], $bop['removed'], $bop['kept']]) ? tf(' · BOP: :c added, :r removed, :k kept', ['c' => $bop['created'], 'r' => $bop['removed'], 'k' => $bop['kept']]) : '');

        return redirect($form)->with('v2_flash', $line);
    }

    /**
     * RESET of the BOP sheet. POST {dry?}. dry = only the counts for the confirmation ({message}); otherwise the sheet goes back to the
     * output of the engine (SheetReset), the engines run again like PROCESS, and one SHEET_RESET decision is logged.
     */
    public function resetOps(Request $r, int $wo, SheetReset $reset, WoPlanningEnricher $enricher, GateService $gates, AutoPlanRunner $runner, PlanChangeService $changes): JsonResponse
    {
        return $this->resetSheet($r, $wo, 'OP', $reset, $enricher, $gates, $runner, $changes);
    }

    /** RESET of the BOM sheet (same flow; refused with a clear message when the engine gives no rows). */
    public function resetMaterials(Request $r, int $wo, SheetReset $reset, WoPlanningEnricher $enricher, GateService $gates, AutoPlanRunner $runner, PlanChangeService $changes): JsonResponse
    {
        return $this->resetSheet($r, $wo, 'MAT', $reset, $enricher, $gates, $runner, $changes);
    }

    private function resetSheet(Request $r, int $wo, string $kind, SheetReset $reset, WoPlanningEnricher $enricher, GateService $gates, AutoPlanRunner $runner, PlanChangeService $changes): JsonResponse
    {
        $this->schedulerOnly();
        $model = PrdWo::findOrFail($wo);
        if (! $this->woEditable($wo) || ! in_array($model->wo_status, ['DRAFT', 'SIMULATED'], true)) {
            return response()->json(['error' => t('This Work Order is already on the main schedule; the sheet cannot be reset.')], 422);
        }
        $n = $reset->preview($model, $kind);
        if ($kind === 'MAT' && $n['engine_rows'] === 0) {
            return response()->json(['error' => t('The engine gives no rows; the BOM is not changed.')], 422);
        }
        $tail = $n['untouched'] > 0 ? ' '.tf(':n started or confirmed operations stay as they are.', ['n' => $n['untouched']]) : '';
        $message = tf('RESET returns the sheet to the engine output. Deleted rows that come back: :r · values back to the engine numbers: :v · rows you added that are removed: :d.', ['r' => $n['restored'], 'v' => $n['values'], 'd' => $n['dropped']]).$tail;
        if ($r->boolean('dry')) {
            return response()->json(['message' => $message, 'counts' => $n]);
        }
        try {
            $reset->run($model, $kind, $this->login());
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        $enricher->enrich($model->fresh());
        $gates->apply($model->fresh());
        $runner->run('MANUAL', $this->login());
        $changes->clearPending();
        $model->fresh()->forceFill(['process_hash' => Ppic::fingerprint($wo), 'processed_at' => now()])->save();
        Ppic::forget();
        session()->flash('v2_flash', $message);

        return response()->json(['rows' => $kind === 'OP' ? Ppic::bopSheet($wo) : Ppic::bomSheet($wo), 'reset' => $n, 'message' => $message]);
    }

    /** Earliest start of a Work Order: the engine does not start any of its operations before it (applied by PROCESS). Empty = no floor. */
    public function earliestStart(Request $r, int $wo): RedirectResponse
    {
        $this->schedulerOnly();
        $form = route('v2.screen', 'production-wo').'?id='.$wo;
        $d = $r->validate(['earliest_start' => ['nullable', 'date']], [], ['earliest_start' => t('Earliest start')]);
        if (! $this->woEditable($wo)) {
            return redirect($form)->withErrors(['order' => t('This Work Order is already on the main schedule; the earliest start is read only.')]);
        }
        PrdWo::whereKey($wo)->update(['earliest_start' => ! empty($d['earliest_start']) ? CarbonImmutable::parse($d['earliest_start']) : null, 'updated_by' => $this->login()]);
        Ppic::forget();

        return redirect($form)->with('v2_flash', t('Earliest start saved. PROCESS plans the Work Order from it.'));
    }

    /** Throw the queued drags away (PROCESS stays as it was). */
    public function discardPending(Request $r, PlanChangeService $changes): RedirectResponse
    {
        $this->schedulerOnly();
        $n = $changes->discardPending($this->login(), $r->query('wo') ? (int) $r->query('wo') : null);
        Ppic::forget();
        $form = route('v2.screen', 'production-wo').($r->query('wo') ? '?id='.(int) $r->query('wo').'&open=sim' : '');

        return redirect($form)->with('v2_flash', tf(':n pending changes discarded.', ['n' => $n]));
    }

    /** Simulation drag: queue the move as a pending change (the engine runs on PROCESS). */
    public function queueMoveOp(Request $r, PlanChangeService $changes): JsonResponse
    {
        $this->schedulerOnly();
        [$op, $machine, $start] = $this->move($r);
        try {
            $n = $changes->queueMove($op, $machine, $start, (string) $r->input('reason', ''), $this->login());
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'reason' => $e->getMessage()]);
        }
        Ppic::forget();

        return response()->json(['ok' => true, 'draft' => 'PENDING '.$n, 'reload' => true]);
    }

    /** A Work Order that is still only planned (no confirmed operation, nothing started) can be changed or deleted. */
    private function woEditable(int $wo): bool
    {
        $model = PrdWo::find($wo);

        return $model && in_array($model->wo_status, ['DRAFT', 'SIMULATED', 'SCHEDULED'], true)
            && ! PrdWoOperation::where('wo_id', $wo)->where(fn ($q) => $q->where('plan_status', 'CONFIRMED')->orWhereNotIn('task_status', ['PENDING', 'ASSIGNED']))->exists();
    }

    /** The target (pieces per hour) of one operation, written with its reason; the engines plan again. Only while the Work Order is not confirmed. */
    private function applyTarget(int $wo, int $operationId, float $target, string $reason): void
    {
        $op = PrdWoOperation::where('wo_id', $wo)->where('operation_id', $operationId)->firstOrFail();
        $old = $op->rate_band_value;
        $op->forceFill(['rate_band_value' => $target])->save();
        DB::connection('tm2prd')->table('prd_wo_operation_machine')->where('operation_id', $op->operation_id)->update(['target_qty_per_hour' => $target]);
        PrdPlanDecision::create(['operation_id' => $op->operation_id, 'actor' => $this->login(), 'event' => 'TARGET', 'old_value' => (string) $old, 'new_value' => (string) $target, 'reason' => $reason]);
        PlanEvents::replan('target changed on '.$op->wo_no);
        Ppic::forget();
    }

    /** Why a new operation order is refused (engine rules known today: printing before post-press / finishing, packing last). */
    private function sequenceViolation(array $opIdsInOrder): ?string
    {
        $rows = DB::connection('tm2prd')->table('prd_wo_operation')->whereIn('operation_id', $opIdsInOrder)->get(['operation_id', 'process_id', 'process_name'])->keyBy('operation_id');
        $stage = DB::connection('tm2mst')->table('prod_process')->whereIn('id_process', $rows->pluck('process_id')->filter()->unique())->pluck('process_stage', 'id_process');
        $seq = [];
        foreach ($opIdsInOrder as $i => $id) {
            $r = $rows[$id];
            $name = strtoupper((string) $r->process_name);
            $st = strtoupper((string) ($stage[$r->process_id] ?? ''));
            $seq[] = ['i' => $i, 'name' => $name, 'stage' => str_starts_with($name, 'QC') ? 'QC' : $st];
        }
        foreach ($seq as $a) {
            foreach ($seq as $b) {
                if ($a['stage'] === 'PRESS' && in_array($b['stage'], ['POSTPRESS', 'FINISHING'], true) && $a['i'] > $b['i']) {
                    return $a['name'].' (printing) must run before '.$b['name'].'.';
                }
                if ($a['stage'] === 'PACKING' && in_array($b['stage'], ['PRESS', 'POSTPRESS', 'FINISHING'], true) && $a['i'] < $b['i']) {
                    return $a['name'].' (packing) must come after '.$b['name'].'.';
                }
            }
        }

        return null;
    }

    /** BOP table: drag a row. POST {order: [operation ids], reason} -> {rows} or {error}. A pending change until PROCESS; only this Work Order's copy of the BOP changes (ProDev is not touched). */
    public function reorderOps(Request $r, int $wo, PlanChangeService $changes): JsonResponse
    {
        $this->schedulerOnly();
        if (! $this->woEditable($wo)) {
            return response()->json(['error' => t('This Work Order is already on the main schedule; the order is locked.')], 422);
        }
        $order = array_map('intval', (array) $r->input('order', []));
        $reason = trim((string) $r->input('reason', ''));
        $have = PrdWoOperation::where('wo_id', $wo)->pluck('operation_id')->map(fn ($i) => (int) $i)->all();
        if ($reason === '') {
            return response()->json(['error' => t('Reason').': '.t('required when the order changes.')], 422);
        }
        if (count($order) !== count($have) || array_diff($order, $have) || array_diff($have, $order)) {
            return response()->json(['error' => t('The order must list every operation of this Work Order once.')], 422);
        }
        if ($why = $this->sequenceViolation($order)) {
            return response()->json(['error' => t($why)], 422);
        }
        $changes->queueOrder($wo, $order, $reason, $this->login());
        Ppic::forget();

        return response()->json(['rows' => Ppic::bopSheet($wo), 'reload' => true]);
    }

    /**
     * BOP table, edited in place. POST {changed: [{id, target, actor}], reason} -> {rows} (latest) or {error}.
     * Target / hour needs the one reason the sheet asks at Save (planned again by the engines, only while the Work Order is not applied);
     * Crew / Subcon ('C:<work center>' | 'S:<vendor>' | '') is saved at once, with no reason and no replan.
     */
    public function targetCells(Request $r, int $wo, WoPlanningEnricher $enricher, PlanChangeService $changes): JsonResponse
    {
        $this->schedulerOnly();
        $editable = $this->woEditable($wo);
        $current = collect(Ppic::bopSheet($wo))->keyBy('id');
        $reason = trim((string) $r->input('reason', ''));
        $options = Ppic::actorOptions();
        $done = 0;
        foreach ((array) $r->input('changed', []) as $c) {
            if (str_starts_with((string) ($c['id'] ?? ''), 'new')) {       // Add row: a new process
                if (! $editable) {
                    return response()->json(['error' => t('This Work Order is already on the main schedule; rows cannot be added.')], 422);
                }
                if ($err = $this->addOperation($wo, $c, $reason, $enricher)) {
                    return $err;
                }
                $done++;

                continue;
            }
            $row = $current->get((int) ($c['id'] ?? 0));
            if (! $row) {
                continue;
            }
            if (array_key_exists('actor', $c) && (string) $c['actor'] !== (string) $row['actor']) {
                if ((string) $c['actor'] !== '' && ! array_key_exists((string) $c['actor'], $options)) {
                    return response()->json(['error' => t('Crew / Subcon').': '.t('not found.')], 422);
                }
                PrdWoOperation::where('wo_id', $wo)->findOrFail((int) $c['id'])->assignActor((string) $c['actor']);
                $done++;
            }
            if ($err = $this->applyTimes($wo, $row, $c, $editable, $reason, $changes)) {
                return $err;
            }
            $done += (int) $this->timesChanged($row, $c);
            $target = isset($c['target']) && $c['target'] !== '' ? (float) $c['target'] : null;
            if ($target === null || abs($target - (float) ($row['target'] ?? 0)) <= 0.0049) {
                continue;
            }
            if (! $editable) {
                return response()->json(['error' => t('This Work Order is already on the main schedule; the target is read only.')], 422);
            }
            if ($target <= 0) {
                return response()->json(['error' => t('Target / hour').': '.t('must be greater than 0.')], 422);
            }
            if ($reason === '') {
                return response()->json(['error' => t('Reason').': '.t('required when the target is changed.'), 'reason_required' => true], 422);
            }
            $this->applyTarget($wo, (int) $c['id'], $target, mb_substr($reason, 0, 190));
            $done++;
        }
        Ppic::forget();

        return response()->json(['rows' => Ppic::bopSheet($wo), 'saved' => $done, 'reload' => true]);
    }

    private function timesChanged(array $row, array $c): bool
    {
        return (isset($c['start']) && (string) $c['start'] !== (string) $row['start']) || (isset($c['finish']) && (string) $c['finish'] !== (string) $row['finish']);
    }

    /**
     * Start / Stop typed in the BOP sheet (Jo 2026-10-13): Start moves the operation on its machine through the same impact engine as a Gantt drag
     * (queued as a pending change until PROCESS); Stop changes the run time, so target / hour is scaled by old run / new run and the engines plan again.
     * Refused with the engine reason when the move is not allowed (clash, gates, locked) or the Work Order is already on the main schedule.
     */
    private function applyTimes(int $wo, array $row, array $c, bool $editable, string $reason, PlanChangeService $changes): ?JsonResponse
    {
        if (! $this->timesChanged($row, $c)) {
            return null;
        }
        if (! $editable) {
            return response()->json(['error' => t('This Work Order is already on the main schedule; Start and Stop are read only.')], 422);
        }
        $op = collect(Ppic::workOrder($wo)['ops'] ?? [])->firstWhere('op_id', (int) $row['id']);
        $parse = fn ($v) => $v === null || $v === '' ? null : CarbonImmutable::parse((string) $v);
        $reason = $reason !== '' ? $reason : 'Start / Stop edited in the BOP sheet';
        $start = $op['start'] ?? null;
        try {
            $newStart = isset($c['start']) && (string) $c['start'] !== (string) $row['start'] ? $parse($c['start']) : null;
            if ($newStart) {
                if (! ctype_digit((string) $row['machine'])) {
                    return response()->json(['error' => t('Start').': '.t('a subcontract step has no machine to move on.')], 422);
                }
                $changes->queueMove((int) $row['id'], (int) $row['machine'], $newStart, $reason, $this->login());
                $start = $newStart;
            }
            $stop = isset($c['finish']) && (string) $c['finish'] !== (string) $row['finish'] ? $parse($c['finish']) : null;
            if ($stop) {
                $run = (float) ($op['run'] ?? 0);
                $new = $run + ($stop->getTimestamp() - $op['finish']->getTimestamp()) / 60 - ($newStart ? ($newStart->getTimestamp() - $op['start']->getTimestamp()) / 60 : 0);
                if ($run <= 0 || $new <= 0 || ! $op['target'] || ! $start) {
                    return response()->json(['error' => t('Stop').': '.t('must be later than Start plus setup.')], 422);
                }
                $this->applyTarget($wo, (int) $row['id'], round((float) $op['target'] * $run / $new, 2), mb_substr($reason, 0, 190));
            }
        } catch (\DomainException $e) {
            return response()->json(['error' => t($e->getMessage())], 422);
        }

        return null;
    }

    /**
     * BOP Add row: one process added to this Work Order by the officer. Machine empty = subcontract; a machine must be one that can run the
     * process (prod_process_machine). Target / hour and Setup default from the capability row, else the process master. The row is marked in
     * the decision log (ROW_ADDED) so PROCESS keeps it; its place follows the process master order (routing_seq). A reason is required once.
     */
    private function addOperation(int $wo, array $c, string $reason, WoPlanningEnricher $enricher): ?JsonResponse
    {
        $process = ProdProcess::query()->where('activate', 1)->find((int) ($c['process'] ?? 0));
        if (! $process) {
            return response()->json(['error' => t('Process').': '.t('choose a process.')], 422);
        }
        $machineId = in_array((string) ($c['machine'] ?? ''), ['', 'subcon'], true) ? null : (int) $c['machine'];
        $cap = $machineId ? DB::connection('tm2mst')->table('prod_process_machine')->where('process_id', $process->id_process)->where('machine_id', $machineId)->where('activate', 1)->first() : null;
        if ($machineId && ! $cap) {
            return response()->json(['error' => t('Machine').': '.t('this machine cannot run the process.')], 422);
        }
        if ($reason === '') {
            return response()->json(['error' => t('Reason').': '.t('required when a row is added.'), 'reason_required' => true], 422);
        }
        $model = PrdWo::findOrFail($wo);
        $ops = PrdWoOperation::where('wo_id', $wo)->orderBy('seq_no')->orderBy('operation_id')->get();
        $routing = ProdProcess::whereIn('id_process', $ops->pluck('process_id')->filter()->unique())->pluck('routing_seq', 'id_process');
        // standard order of the process master: after the last operation whose process comes before or with this one
        $at = 0;
        foreach ($ops as $i => $o) {
            if (isset($routing[$o->process_id]) && (int) $routing[$o->process_id] <= (int) $process->routing_seq) {
                $at = $i + 1;
            }
        }
        $prev = $ops[$at - 1] ?? null;
        $target = isset($c['target']) && $c['target'] !== '' ? (float) $c['target'] : ($cap->speed_per_hour ?? $process->default_speed_per_hour);

        DB::connection('tm2prd')->transaction(function () use ($model, $ops, $at, $prev, $process, $machineId, $cap, $target, $reason, $c) {
            $op = PrdWoOperation::create([
                'wo_id' => $model->wo_id, 'seq_no' => 0, 'process_name' => $process->process_name, 'process_id' => $process->id_process,
                'resource_type' => $machineId ? 'MACHINE' : 'MANUAL', 'machine_id' => $machineId,
                'qty' => $prev ? (float) $prev->qty : (float) $model->qty, 'task_status' => 'PENDING', 'plan_status' => 'NONE',
                'rate_band_value' => $target ?: null, 'setup_base_min' => $cap->setup_minutes ?? $process->default_setup_minutes,
                'created_by' => $this->login(),
            ]);
            $order = $ops->pluck('operation_id')->all();
            array_splice($order, $at, 0, [$op->operation_id]);
            foreach ($order as $i => $id) {
                PrdWoOperation::whereKey($id)->update(['seq_no' => ($i + 1) * 10]);
            }
            if (isset($c['actor']) && $c['actor'] !== '') {
                $op->refresh()->assignActor((string) $c['actor']);
            }
            PrdPlanDecision::create(['operation_id' => $op->operation_id, 'actor' => $this->login(), 'event' => 'ROW_ADDED',
                'old_value' => json_encode(['wo' => (int) $model->wo_id, 'kind' => 'OP', 'key' => (int) $op->operation_id]), 'new_value' => mb_substr($process->process_name, 0, 190), 'reason' => mb_substr($reason, 0, 190)]);
        });
        $enricher->enrich($model->fresh());
        Ppic::forget();

        return null;
    }

    /**
     * BOP Delete row. POST {ids, reason}. A confirmed / started operation, or one with output, is refused; so is a process whose materials
     * are still in the BOM. A row the engine made needs a reason and is remembered as deleted (PROCESS does not write it again).
     */
    public function deleteOps(Request $r, int $wo): JsonResponse
    {
        $this->schedulerOnly();
        if (! $this->woEditable($wo)) {
            return response()->json(['error' => t('This Work Order is already on the main schedule; rows cannot be deleted.')], 422);
        }
        $ids = array_map('intval', (array) $r->input('ids', []));
        $reason = trim((string) $r->input('reason', ''));
        $ops = PrdWoOperation::where('wo_id', $wo)->whereIn('operation_id', $ids)->get();
        $added = Ppic::addedRows($wo, 'OP');
        $engineRows = 0;
        foreach ($ops as $o) {
            $started = $o->plan_status === 'CONFIRMED' || ! in_array($o->task_status, ['PENDING', 'ASSIGNED'], true)
                || DB::connection('tm2prd')->table('prd_wo_operation_output')->where('operation_id', $o->operation_id)->exists()
                || DB::connection('tm2prd')->table('prd_breakdown_event')->where('operation_id', $o->operation_id)->exists();
            if ($started) {
                return response()->json(['error' => $o->process_name.': '.t('cannot be deleted, it is confirmed or already started.')], 422);
            }
            $lastOfProcess = ! PrdWoOperation::where('wo_id', $wo)->where('process_id', $o->process_id)->where('operation_id', '!=', $o->operation_id)->whereNotIn('operation_id', $ids)->exists();
            if ($o->process_id && $lastOfProcess && PrdWoMaterial::where('wo_id', $wo)->where('process_id', $o->process_id)->exists()) {
                return response()->json(['error' => $o->process_name.': '.t('delete the materials of this process first.')], 422);
            }
            $engineRows += in_array((int) $o->operation_id, $added, true) ? 0 : 1;
        }
        if ($engineRows > 0 && $reason === '') {
            return response()->json(['error' => t('Reason').': '.t('required when a row of the engine is deleted.'), 'reason_required' => true], 422);
        }
        DB::connection('tm2prd')->transaction(function () use ($ops, $added, $wo, $reason) {
            foreach ($ops as $o) {
                if (! in_array((int) $o->operation_id, $added, true)) {   // engine row: remember it as deleted
                    PrdPlanDecision::create(['actor' => $this->login(), 'event' => 'ROW_DELETED', 'new_value' => mb_substr($o->process_name, 0, 190), 'reason' => mb_substr($reason, 0, 190),
                        'old_value' => json_encode(['wo' => $wo, 'kind' => 'OP', 'key' => (string) $o->process_id])]);
                }
                DB::connection('tm2prd')->table('prd_wo_operation_machine')->where('operation_id', $o->operation_id)->delete();
                DB::connection('tm2prd')->table('prd_crew_assignment')->where('operation_id', $o->operation_id)->delete();
                $o->delete();
            }
        });
        Ppic::forget();

        return response()->json(['rows' => Ppic::bopSheet($wo), 'deleted' => $ops->count()]);
    }

    /** BOM Delete row: free lines may go; a line the engine made needs a reason and is remembered as deleted (PROCESS does not write it again). */
    public function deleteMaterials(Request $r, int $wo, GateService $gates): JsonResponse
    {
        $this->schedulerOnly();
        if (! $this->woEditable($wo)) {
            return response()->json(['error' => t('This Work Order is already on the main schedule; rows cannot be deleted.')], 422);
        }
        $ids = array_map('intval', (array) $r->input('ids', []));
        $reason = trim((string) $r->input('reason', ''));
        $lines = PrdWoMaterial::where('wo_id', $wo)->whereIn('detail_id', $ids)->get();
        $added = Ppic::addedRows($wo, 'MAT');
        $engine = $lines->filter(fn ($l) => ! in_array((int) $l->detail_id, $added, true));
        if ($engine->isNotEmpty() && $reason === '') {
            return response()->json(['error' => t('Reason').': '.t('required when a row of the engine is deleted.'), 'reason_required' => true], 422);
        }
        DB::connection('tm2prd')->transaction(function () use ($lines, $engine, $wo, $reason) {
            foreach ($lines as $l) {
                if ($engine->contains('detail_id', $l->detail_id)) {
                    PrdPlanDecision::create(['actor' => $this->login(), 'event' => 'ROW_DELETED', 'new_value' => mb_substr((string) $l->material_name, 0, 190), 'reason' => mb_substr($reason, 0, 190),
                        'old_value' => json_encode(['wo' => $wo, 'kind' => 'MAT', 'key' => $l->material_role.'|'.$l->material_name])]);
                }
                $l->delete();
            }
        });
        $gates->apply(PrdWo::findOrFail($wo));
        Ppic::forget();

        return response()->json(['rows' => Ppic::bomSheet($wo), 'deleted' => $lines->count()]);
    }

    /**
     * BOM Add row: one material line added by the officer. Process = one of this Work Order's BOP processes; Material = typed code or name
     * of the item master (one match); Role from the engine's roles; Required a number. Release = Available and Purchase = max(0, Required - Release)
     * like every line. Marked ROW_ADDED so PROCESS keeps it. A reason is required once.
     */
    private function addMaterial(int $wo, array $c, string $reason): ?JsonResponse
    {
        $text = trim((string) ($c['material'] ?? ''));
        $role = trim((string) ($c['role'] ?? ''));
        $req = $c['required'] ?? '';
        if ($text === '' || $role === '' || ! is_numeric($req) || (float) $req < 0) {
            return response()->json(['error' => t('Material').', '.t('Role').', '.t('Required').': '.t('fill all of them.')], 422);
        }
        $mst = DB::connection('tm2mst')->table('mat_raw')->where('activate', 1);
        $hit = (clone $mst)->where(fn ($q) => $q->where('material_code', $text)->orWhereRaw('LOWER(material_name) = ?', [mb_strtolower($text)]))->limit(2)->get(['material_id', 'material_name', 'uom_stock']);
        if ($hit->isEmpty()) {
            $hit = (clone $mst)->where('material_name', 'like', '%'.$text.'%')->orderBy('material_name')->limit(6)->get(['material_id', 'material_name', 'uom_stock']);
        }
        if ($hit->isEmpty()) {
            return response()->json(['error' => t('Material').': '.t('not found in the item master.')], 422);
        }
        if ($hit->count() > 1) {
            return response()->json(['error' => t('Several materials match').': '.$hit->pluck('material_name')->implode(' · ').'. '.t('Type more of the name or the item code.')], 422);
        }
        if ($reason === '') {
            return response()->json(['error' => t('Reason').': '.t('required when a row is added.'), 'reason_required' => true], 422);
        }
        $m = $hit->first();
        $process = (int) ($c['process'] ?? 0);
        if ($process && ! PrdWoOperation::where('wo_id', $wo)->where('process_id', $process)->exists()) {
            return response()->json(['error' => t('Process').': '.t('not in the BOP of this Work Order.')], 422);
        }
        DB::connection('tm2prd')->transaction(function () use ($wo, $m, $role, $process, $req, $reason) {
            $line = PrdWoMaterial::create([
                'wo_id' => $wo, 'seq_no' => (int) PrdWoMaterial::where('wo_id', $wo)->max('seq_no') + 10, 'material_id' => $m->material_id,
                'material_role' => mb_substr($role, 0, 20), 'process_id' => $process ?: null, 'material_name' => $m->material_name, 'uom' => $m->uom_stock,
                'qty_required' => (float) $req, 'qty_standar' => 0, 'override_reason' => mb_substr($reason, 0, 190),
                'material_needed_by' => PrdWoMaterial::where('wo_id', $wo)->value('material_needed_by'), 'created_by' => $this->login(),
            ]);
            PrdPlanDecision::create(['actor' => $this->login(), 'event' => 'ROW_ADDED', 'new_value' => mb_substr((string) $m->material_name, 0, 190), 'reason' => mb_substr($reason, 0, 190),
                'old_value' => json_encode(['wo' => $wo, 'kind' => 'MAT', 'key' => (int) $line->detail_id])]);
        });

        return null;
    }

    /**
     * Bill of Material tab: the officer edits Required, Release or Purchase. Formula (Jo 2026-10-13): Release = Available (the default),
     * Purchase = max(0, Required - Release). Release may be 0..Available; editing Purchase sets Release = Required - Purchase (which must
     * also stay within 0..Available). SEBELUM: Release 0..Required with Required = Release + Purchase. A line that differs from the engine
     * defaults needs one reason; qty_standar keeps the engine number. Only while the Work Order is not confirmed.
     */
    public function bomCells(Request $r, int $wo, GateService $gates): JsonResponse
    {
        $this->schedulerOnly();
        if (! $this->woEditable($wo)) {
            return response()->json(['error' => t('This Work Order is already on the main schedule; the material quantities are read only.')], 422);
        }
        $current = collect(Ppic::bomSheet($wo))->keyBy('id');
        $changes = [];
        $procSet = [];
        $added = 0;
        foreach ((array) $r->input('changed', []) as $c) {
            if (str_starts_with((string) ($c['id'] ?? ''), 'new')) {       // Add row: a new material
                if ($err = $this->addMaterial($wo, $c, trim((string) $r->input('reason', '')))) {
                    return $err;
                }
                $added++;

                continue;
            }
            $line = PrdWoMaterial::where('wo_id', $wo)->where('detail_id', (int) ($c['id'] ?? 0))->first();
            $was = $current->get((int) ($c['id'] ?? 0));
            if (! $line || ! $was) {
                return response()->json(['error' => t('Material line not found.')], 422);
            }
            foreach (['required', 'release', 'purchase', 'waste', 'spill'] as $k) {
                if (isset($c[$k]) && $c[$k] !== '' && (! is_numeric($c[$k]) || (float) $c[$k] < 0)) {
                    return response()->json(['error' => t(ucfirst($k)).': '.t('must be a number of 0 or more.')], 422);
                }
            }
            // a line without process (the engine gave none) takes one of this Work Order's BOP processes chosen by the officer
            $pid = (int) ($c['process'] ?? 0);
            if ($pid && $line->process_id === null) {
                if (! PrdWoOperation::where('wo_id', $wo)->where('process_id', $pid)->exists()) {
                    return response()->json(['error' => t('Process').': '.t('not in the BOP of this Work Order.')], 422);
                }
                $procSet[] = [$line, $pid];
            }
            $moved = fn (string $k) => isset($c[$k]) && $c[$k] !== '' && abs((float) $c[$k] - (float) $was[$k]) > 0.0049;
            // Waste / Spill typed by the officer: kept as an override of the ProDev value; Required (which already carries them) moves by the same difference.
            $ov = [];
            $shift = 0.0;
            foreach (['waste', 'spill'] as $k) {
                if ($moved($k)) {
                    $ov[$k.'_override_qty'] = round((float) $c[$k], 4);
                    $shift += (float) $c[$k] - (float) ($was[$k] ?? 0);
                }
            }
            $req = $moved('required') ? (float) $c['required'] : (float) $was['required'] + $shift;
            $rel = (float) $was['release'];
            if ($moved('release') && $moved('purchase') && abs(GateService::purchase($req, (float) $c['release']) - (float) $c['purchase']) > 0.0149) {
                return response()->json(['error' => t('Purchase must be Required minus Release.')], 422);
            }
            if ($moved('release')) {
                $rel = (float) $c['release'];
            } elseif ($moved('purchase')) {
                $rel = $req - (float) $c['purchase'];
            }
            $avail = (float) $was['avail_qty'];
            if ($rel < -0.0049 || $rel > $avail + 0.0049) {
                return response()->json(['error' => t('Release must be between 0 and Available.')], 422);
            }
            $rel = max(0.0, min($rel, $avail));
            $defaultRel = $avail;
            $differs = abs($req - (float) $line->qty_standar) > 0.00005 || abs($rel - $defaultRel) > 0.00005;
            $changes[] = [$line, round($req, 4), round($rel, 4), $differs, $ov];
        }
        $reason = trim((string) $r->input('reason', ''));
        if ($reason === '' && collect($changes)->contains(fn ($x) => $x[3])) {
            return response()->json(['error' => t('Reason').': '.t('required when a quantity differs from the engine.'), 'reason_required' => true], 422);
        }
        DB::connection('tm2prd')->transaction(function () use ($changes, $reason, $procSet) {
            foreach ($procSet as [$line, $pid]) {
                $line->forceFill(['process_id' => $pid])->save();
                PrdPlanDecision::create(['actor' => $this->login(), 'event' => 'MATERIAL_PROCESS', 'old_value' => mb_substr((string) $line->material_name, 0, 190), 'new_value' => (string) $pid, 'reason' => 'Process chosen by the officer']);
            }
            foreach ($changes as [$line, $req, $rel, $differs, $ov]) {
                $old = (float) $line->qty_required;
                // an override equal to the ProDev value is no override
                foreach (['waste', 'spill'] as $k) {
                    if (isset($ov[$k.'_override_qty']) && abs($ov[$k.'_override_qty'] - (float) $line->{$k.'_engine_qty'}) < 0.00005) {
                        $ov[$k.'_override_qty'] = null;
                    }
                }
                $line->forceFill($ov);
                $line->forceFill($differs
                    ? ['qty_required' => $req, 'qty_release' => $rel, 'qty_purchase' => round(GateService::purchase($req, $rel), 4), 'override_reason' => mb_substr($reason, 0, 190)]
                    : ['qty_required' => $req, 'qty_release' => null, 'qty_purchase' => null, 'override_reason' => null])->save();
                PrdPlanDecision::create(['actor' => $this->login(), 'event' => 'MATERIAL_QTY', 'old_value' => mb_substr($line->material_name.' '.$old, 0, 190),
                    'new_value' => 'required '.$req.' · release '.$rel.' · purchase '.round(GateService::purchase($req, $rel), 4), 'reason' => $differs ? mb_substr($reason, 0, 190) : 'Back to the engine quantity']);
            }
        });
        $gates->apply(PrdWo::findOrFail($wo));
        Ppic::forget();

        return response()->json(['rows' => Ppic::bomSheet($wo), 'saved' => count($changes) + count($procSet) + $added]);
    }

    /** Work Order form: delete (cancel) with a reason. A confirmed Work Order cannot be deleted. */
    public function deleteWo(Request $r, int $wo): RedirectResponse
    {
        $this->schedulerOnly();
        $d = $r->validate(['reason' => ['required', 'string', 'max:190']], [], ['reason' => t('Reason')]);
        if (! $this->woEditable($wo)) {
            return redirect(route('v2.screen', 'production-wo').'?id='.$wo)->withErrors(['delete' => t('A confirmed Work Order cannot be deleted.')]);
        }
        $model = PrdWo::findOrFail($wo);
        DB::connection('tm2prd')->transaction(function () use ($model, $d) {
            PrdWoOperation::where('wo_id', $model->wo_id)->update(['plan_status' => 'NONE', 'draft_machine_id' => null, 'draft_start' => null, 'draft_finish' => null, 'draft_run_id' => null]);
            $model->forceFill(['wo_status' => 'CANCELLED', 'cancel_reason' => $d['reason']])->save();
            // The delivery line goes back to the picker unless another live Work Order still comes from it.
            if ($model->source_delivery_id && ! PrdWo::where('source_delivery_id', $model->source_delivery_id)->where('wo_status', '!=', 'CANCELLED')->exists()) {
                CoOrderDelivery::whereKey($model->source_delivery_id)->update(['posted_at' => null, 'posted_by' => null]);
            }
            PrdPlanDecision::create(['actor' => $this->login(), 'event' => 'WO_DELETE', 'old_value' => (string) $model->wo_no, 'reason' => $d['reason']]);
        });
        Ppic::forget();

        return redirect(route('v2.screen', 'production-wo'))->with('v2_flash', tf('Work Order :no deleted.', ['no' => $model->wo_no]));
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

    // ------------------------------------------------------------------ Production Output (tablet at the machine)

    /** Play / Pause / Stop of one timer (setup | prod); answers the state of both timers so the screen redraws. Every crew member may act; the action is logged. */
    public function outputTimer(Request $r, int $op, OperatorTimerService $timer): JsonResponse
    {
        $d = $r->validate(['timer' => ['required', 'in:setup,prod'], 'action' => ['required', 'in:play,pause,stop']]);
        try {
            $state = $timer->act($op, $d['timer'], $d['action'], $this->login());
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        Ppic::forget();

        return response()->json(['timers' => $state]);
    }

    /** SIMPAN HASIL: GOOD and BAD are added to what is saved for the operation. */
    public function outputResult(Request $r, int $op, OperatorTimerService $timer): JsonResponse
    {
        $d = $r->validate(['good' => ['nullable', 'numeric', 'min:0'], 'bad' => ['nullable', 'numeric', 'min:0'],
            'defects' => ['nullable', 'array'], 'defects.*.scrap_type_id' => ['required', 'integer'], 'defects.*.qty' => ['required', 'numeric', 'gt:0']]);
        try {
            $saved = $timer->saveResult($op, (float) ($d['good'] ?? 0), (float) ($d['bad'] ?? 0), $this->login(), $d['defects'] ?? []);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        Ppic::forget();

        return response()->json(['saved' => $saved]);
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
