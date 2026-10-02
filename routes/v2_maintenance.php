<?php

use App\Models\Master\MatRaw;
use App\Models\Prd\MntLog;
use App\Models\Prd\MntPlan;
use App\Models\Prd\MntPlanPart;
use App\Models\Prd\MntRequest;
use App\Models\Prd\MntWo;
use App\Services\Maintenance\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Maintenance v2 (docs/maintenance_tier1.md). Logika = App\Services\Maintenance\MaintenanceService.
// File route sendiri (dimuat routes/web.php sesudah v2.php) supaya tidak bercampur dengan pekerjaan modul lain di v2.php.
// Keputusan (Accept/Reject request, Confirm/Back to Draft/Close WO, simpan plan) = hak approve modul MAINT.
Route::prefix('v2/maintenance')->name('v2.mnt.')->middleware('module:MAINT')->group(function () {
    $svc = fn () => new MaintenanceService(Auth::user()->login);
    $head = fn () => abort_unless(MaintenanceService::canDecide(Auth::user()), 403, t('Only the Head of Maintenance (approve right on the Maintenance module).'));
    $wo = fn (int $id) => route('v2.screen', 'maintenance-wo').'?id='.$id;
    $req = fn (int $id) => route('v2.screen', 'maintenance-request').'?id='.$id;
    $plan = fn (int $id) => route('v2.screen', 'maintenance-plan').'?id='.$id;

    // ------------------------------------------------------------ Request
    Route::post('/request/create', function (Request $r) use ($svc, $req, $wo) {
        $d = $r->validate([
            'machine_id' => ['required', 'integer', 'exists:tm2mst.prod_machine,machine_id'], 'request_kind' => ['required', 'in:'.implode(',', array_keys(MntRequest::KINDS))],
            'symptom' => ['required', 'string', 'max:2000'], 'stopped_at' => ['nullable', 'date'], 'reported_by' => ['nullable', 'integer'],
        ], [], ['machine_id' => t('Machine'), 'symptom' => t('What Happened'), 'request_kind' => t('Kind')]);
        $m = $svc()->createRequest($d + ['machine_stopped' => $r->boolean('machine_stopped')]);
        $back = $r->input('back') === 'my-work' ? route('v2.screen', 'maintenance-my-work') : ($m->wo_id ? $wo($m->wo_id) : $req($m->id_request));

        return redirect($back)->with('v2_flash', $m->wo_id
            ? "{$m->doc_no} saved — priority {$m->priority}, turned straight into Work Order ".$m->wo->doc_no.'.'
            : "{$m->doc_no} saved (priority {$m->priority}) — waiting for the Head of Maintenance.");
    })->name('request.create');

    Route::post('/request/{id}/save', function (Request $r, int $id) use ($svc, $head, $req, $wo) {
        $m = MntRequest::findOrFail($id);
        $action = (string) $r->input('action', 'save');
        if ($action === 'accept') {
            $head();
            $r->validate(['technician_id' => ['nullable', 'integer'], 'planned_start' => ['nullable', 'date']]);
            $w = $svc()->acceptRequest($m, $r->integer('technician_id') ?: null, $r->input('planned_start') ?: null);

            return redirect($wo($w->id_wo))->with('v2_flash', "{$m->doc_no} accepted → Work Order {$w->doc_no} (Scheduled).");
        }
        if ($action === 'reject') {
            $head();
            $svc()->rejectRequest($m, (string) $r->input('_reason', ''));

            return redirect($req($id))->with('v2_flash', "{$m->doc_no} rejected.");
        }
        abort_unless($m->status === 'OPEN', 422, t('This request has already been decided.'));
        $d = $r->validate(['request_kind' => ['required', 'in:'.implode(',', array_keys(MntRequest::KINDS))], 'priority' => ['required', 'in:P1,P2,P3,P4'],
            'symptom' => ['required', 'string', 'max:2000']]);
        $m->update($d + ['updated_by' => Auth::user()->login]);

        return redirect($req($id))->with('v2_flash', 'Request saved.');
    })->name('request.save');

    // ------------------------------------------------------------ Work Order
    Route::post('/wo/create', function (Request $r) use ($svc, $wo) {
        $d = $r->validate(['machine_id' => ['required', 'integer', 'exists:tm2mst.prod_machine,machine_id'], 'wo_type' => ['required', 'in:'.implode(',', array_keys(MntWo::TYPES))],
            'description' => ['required', 'string', 'max:255']], [], ['machine_id' => t('Machine'), 'description' => t('Description')]);
        $w = $svc()->createWo($d);

        return redirect($wo($w->id_wo))->with('v2_flash', 'Draft WO created — complete it, then Confirm.');
    })->name('wo.create');

    Route::post('/wo/generate', function () use ($svc, $head) {
        $head();
        $made = $svc()->generateDue();

        return redirect(route('v2.screen', 'maintenance-wo'))->with('v2_flash', count($made)
            ? count($made).' preventive WO(s) created (Draft): '.collect($made)->map(fn ($w) => $w->description)->implode(', ').' — set the schedule, then Confirm.'
            : 'No plan is due (or its WO already exists).');
    })->name('wo.generate');

    Route::post('/wo/{id}/save', function (Request $r, int $id) use ($svc, $head, $wo) {
        $w = MntWo::findOrFail($id);
        $action = (string) $r->input('action', 'save');
        $s = $svc();
        $r->validate(['planned_start' => ['nullable', 'date'], 'planned_end' => ['nullable', 'date'], 'cost_vendor' => ['nullable', 'numeric', 'min:0'],
            'meter_at_done' => ['nullable', 'numeric', 'min:0'], 'machine_id' => ['nullable', 'integer', 'exists:tm2mst.prod_machine,machine_id']]);
        switch ($action) {
            case 'delete':
                $s->deleteDraft($w);

                return redirect(route('v2.screen', 'maintenance-wo'))->with('v2_flash', 'Draft WO deleted.');
            case 'cancel':
                $s->cancel($w, (string) $r->input('_reason', ''));

                return redirect($wo($id))->with('v2_flash', 'WO cancelled.');
            case 'draft':
                $head();
                $s->backToDraft($w);

                return redirect($wo($id))->with('v2_flash', 'WO is back to Draft.');
            case 'start':
                $s->start($w);

                return redirect($wo($id))->with('v2_flash', 'WO started (In Progress) — downtime and machine status recorded.');
            case 'close':
                $head();
                $s->close($w);

                return redirect($wo($id))->with('v2_flash', 'WO closed — cost is final, next preventive due date calculated.');
        }
        if ($w->docStatus() === 'DRAFT' || $w->isWorking()) {
            $s->saveWo($w, $r->all());
        }
        $w->refresh();
        switch ($action) {
            case 'add_task':
                $s->addTask($w, (string) $r->input('new_task'));
                $msg = 'Task added.';
                break;
            case 'add_part':
                abort_unless($r->filled('material_id'), 422, t('Choose a spare part from the material master.'));
                $s->addPart($w, $r->integer('material_id'), max(0.001, (float) $r->input('new_part_qty', 1)));
                $msg = 'Spare part added.';
                break;
            case 'add_labor':
                abort_unless($r->filled('labor_employee_id'), 422, t('Choose a technician.'));
                $s->addLabor($w, $r->integer('labor_employee_id'), $r->integer('labor_minutes'), $r->input('labor_date') ?: null);
                $msg = 'Labor added.';
                break;
            case 'confirm':
                $head();
                $s->confirm($w);
                $msg = 'WO confirmed: '.$w->refresh()->doc_no.' (Scheduled).';
                break;
            case 'hold':
                $s->hold($w, (string) $r->input('_reason', ''));
                $msg = 'WO on hold (Waiting Part).';
                break;
            case 'resume':
                $s->resume($w);
                $msg = 'WO resumed (In Progress).';
                break;
            case 'done':
                $s->done($w);
                $msg = 'WO done — downtime closed, machine Ready again. Waiting for Close by the Head of Maintenance.';
                break;
            default:
                $msg = 'WO saved.';
        }
        $back = $r->input('back') === 'my-work' ? route('v2.screen', 'maintenance-my-work') : $wo($id);

        return redirect($back)->with('v2_flash', $msg);
    })->name('wo.save');

    // ------------------------------------------------------------ Preventive Plan
    Route::post('/plan/save', function (Request $r) use ($svc, $head, $plan) {
        $head();
        $action = (string) $r->input('action', 'save');
        $p = $r->filled('id') ? MntPlan::findOrFail($r->integer('id')) : null;
        if ($p && in_array($action, ['activate', 'deactivate'], true)) {
            $p->update(['activate' => $action === 'activate', 'updated_by' => Auth::user()->login]);
            MntLog::add('PLAN', $p->id_plan, $action === 'activate' ? 'Activated' : 'Deactivated');

            return redirect($plan($p->id_plan))->with('v2_flash', $action === 'activate' ? 'Plan activated.' : 'Plan deactivated — it no longer creates WOs.');
        }
        $d = $r->validate([
            'plan_code' => ['required', 'string', 'max:40', 'unique:tm2prd.mnt_plan,plan_code'.($p ? ','.$p->id_plan.',id_plan' : '')],
            'plan_name' => ['required', 'string', 'max:150'], 'machine_id' => ['required', 'integer', 'exists:tm2mst.prod_machine,machine_id'],
            'wo_type' => ['required', 'in:PREVENTIVE,INSPECTION'], 'trigger_mode' => ['required', 'in:TIME,METER,TIME_OR_METER'],
            'interval_value' => ['nullable', 'integer', 'min:1', 'required_unless:trigger_mode,METER'], 'interval_unit' => ['nullable', 'in:DAY,WEEK,MONTH,YEAR', 'required_unless:trigger_mode,METER'],
            'meter_type' => ['nullable', 'in:RUN_HOURS,IMPRESSIONS', 'required_unless:trigger_mode,TIME'], 'meter_interval' => ['nullable', 'numeric', 'min:1', 'required_unless:trigger_mode,TIME'],
            'schedule_mode' => ['required', 'in:FLOATING,FIXED'], 'lead_days' => ['nullable', 'integer', 'min:0', 'max:60'], 'est_minutes' => ['nullable', 'integer', 'min:0'],
            'last_done_date' => ['nullable', 'date'], 'last_done_meter' => ['nullable', 'numeric', 'min:0'], 'next_due_date' => ['nullable', 'date'],
            'certificate_valid_until' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['plan_code' => t('Plan code'), 'plan_name' => t('Plan name'), 'machine_id' => t('Machine'), 'interval_value' => t('Every'), 'meter_interval' => t('Or every (meter)')]);
        $login = Auth::user()->login;
        $d['needs_machine_stop'] = $r->boolean('needs_machine_stop');
        $d['lead_days'] = (int) ($d['lead_days'] ?? 3);
        $isNew = ! $p;
        $p = $p ? tap($p)->update($d + ['updated_by' => $login]) : MntPlan::create($d + ['activate' => true, 'created_by' => $login, 'updated_by' => $login]);
        MntLog::add('PLAN', $p->id_plan, $isNew ? 'Created' : 'Saved');

        foreach (array_keys((array) $r->input('remove', [])) as $tid) {
            $p->tasks()->whereKey((int) $tid)->delete();
        }
        foreach ((array) $r->input('tasks', []) as $tid => $row) {
            $p->tasks()->whereKey((int) $tid)->update(['task_text' => $row['task_text'] ?? '', 'task_kind' => $row['task_kind'] ?? 'INSPECT',
                'measure_uom' => ($row['measure_uom'] ?? '') ?: null, 'min_value' => ($row['min_value'] ?? '') === '' ? null : (float) $row['min_value'],
                'max_value' => ($row['max_value'] ?? '') === '' ? null : (float) $row['max_value'], 'is_required' => filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOL),
                'updated_by' => $login]);
        }
        foreach (array_keys((array) $r->input('remove_parts', [])) as $pid) {
            $p->parts()->whereKey((int) $pid)->delete();
        }
        foreach ((array) $r->input('parts', []) as $pid => $row) {
            $p->parts()->whereKey((int) $pid)->update(['qty' => (float) ($row['qty'] ?? 1), 'updated_by' => $login]);
        }
        $msg = 'Plan saved.';
        if ($action === 'add_task' && trim((string) $r->input('new_task')) !== '') {
            $p->tasks()->create(['seq_no' => (int) $p->tasks()->max('seq_no') + 1, 'task_text' => trim((string) $r->input('new_task')),
                'task_kind' => $r->input('new_task_kind', 'INSPECT'), 'is_required' => true, 'created_by' => $login, 'updated_by' => $login]);
            $msg = 'Task added.';
        } elseif ($action === 'add_part' && $r->filled('material_id')) {
            $mat = MatRaw::findOrFail($r->integer('material_id'));
            MntPlanPart::create(['plan_id' => $p->id_plan, 'material_id' => $mat->material_id, 'qty' => max(0.001, (float) $r->input('new_part_qty', 1)),
                'uom_code' => $mat->uom_stock ?: $mat->uom_purchase, 'created_by' => $login, 'updated_by' => $login]);
            $msg = 'Spare part added.';
        }
        // Jatuh tempo berikutnya selalu dihitung sistem (bukan diketik), kecuali FIXED yang tanggal pertamanya diisi user.
        $p->refresh();
        if (! ($p->schedule_mode === 'FIXED' && $r->filled('next_due_date'))) {
            $p->update($svc()->nextDue($p, $p->last_done_date ? CarbonImmutable::parse($p->last_done_date) : null, $p->last_done_meter));
        }
        if ($action === 'generate') {
            $w = $svc()->generateFromPlan($p->refresh()->load(['tasks', 'parts']), true);
            $msg = $w ? 'Preventive WO created (Draft) from this plan.' : 'This plan still has an open WO or is inactive — no new WO created.';
            if ($w) {
                return redirect(route('v2.screen', 'maintenance-wo').'?id='.$w->id_wo)->with('v2_flash', $msg);
            }
        }

        return redirect($plan($p->id_plan))->with('v2_flash', $msg);
    })->name('plan.save');
});
