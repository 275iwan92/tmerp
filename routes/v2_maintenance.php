<?php

use App\Models\Master\MatRaw;
use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntAssetPart;
use App\Models\Mnt\MntDailyCheck;
use App\Models\Mnt\MntDocument;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntOrder;
use App\Models\Mnt\MntPlan;
use App\Models\Mnt\MntPlanPart;
use App\Models\Mnt\MntPlanTask;
use App\Models\Mnt\MntWatchPoint;
use App\Services\Maintenance\AssetProfileService;
use App\Services\Maintenance\DailyCheckService;
use App\Services\Maintenance\MaintenanceAccess;
use App\Services\Maintenance\OrderService;
use App\Services\Maintenance\PlanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Maintenance V2, Fase 1 (Master Data). Logika = App\Services\Maintenance\AssetProfileService.
// File route sendiri (dimuat routes/web.php sesudah v2.php). Mengubah master = hak approve modul MAINT (kepala maintenance) atau administrator.
Route::prefix('v2/maintenance')->name('v2.mnt.')->middleware('module:MAINT')->group(function () {
    $head = fn () => abort_unless(MaintenanceAccess::canManage(Auth::user()), 403, t('Only the head of maintenance (approve right on the Maintenance module) can change this.'));
    $asset = fn (int $id) => route('v2.screen', 'maintenance-profile').'?id='.$id;
    $doc = fn (int $id) => route('v2.screen', 'maintenance-documents').'?id='.$id;
    $num = fn ($v) => ($v === null || $v === '') ? null : (float) $v;

    // ------------------------------------------------------------ Asset Maintenance Profile
    Route::post('/asset/sync', function () use ($head) {
        $head();
        $r = (new AssetProfileService)->syncMachines();

        return redirect(route('v2.screen', 'maintenance-profile'))->with('v2_flash', tf(':n machines added from Master Machine, :u updated.', ['n' => $r['created'], 'u' => $r['updated']]));
    })->name('asset.sync');

    Route::post('/asset/save', function (Request $r) use ($head, $asset, $num) {
        $head();
        $action = (string) $r->input('action', 'save');
        $a = $r->filled('id') ? MntAsset::findOrFail($r->integer('id')) : null;
        $login = Auth::user()->login;

        if ($a && in_array($action, ['activate', 'deactivate'], true)) {
            $a->update(['activate' => $action === 'activate', 'updated_by' => $login]);
            MntLog::add('ASSET', $a->id_asset, $action === 'activate' ? 'Activated' : 'Deactivated');

            return redirect($asset($a->id_asset))->with('v2_flash', $action === 'activate' ? 'Asset activated.' : 'Asset deactivated.');
        }

        // Aset tertaut Master Mesin: kode, nama, kelas dan kritikalitas dibaca dari sana, tidak diubah di sini.
        $linked = $a?->isLinkedMachine() ?? false;
        $rules = ['meter_type' => ['required', Rule::in(array_keys(MntAsset::METERS))], 'location' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000'],
            'fixed_asset_id' => ['nullable', 'integer', 'exists:tm2acc.fin_asset,asset_id']];
        if (! $linked) {
            $rules += ['asset_code' => ['required', 'string', 'max:40', 'unique:tm2mnt.mnt_asset,asset_code'.($a ? ','.$a->id_asset.',id_asset' : '')], 'asset_name' => ['required', 'string', 'max:150'],
                'asset_class' => ['required', Rule::in(['VEHICLE', 'FACILITY', 'SUPPORT', 'MACHINE'])], 'criticality' => ['nullable', Rule::in(['A', 'B', 'C'])]];
        }
        $d = $r->validate($rules, [], ['asset_code' => t('Asset code'), 'asset_name' => t('Asset name'), 'asset_class' => t('Class'), 'meter_type' => t('Meter')]);
        $d['fixed_asset_id'] = ($d['fixed_asset_id'] ?? null) ?: null;
        $isNew = ! $a;
        $a = $a ? tap($a)->update($d + ['updated_by' => $login]) : MntAsset::create($d + ['activate' => true, 'created_by' => $login, 'updated_by' => $login]);
        MntLog::add('ASSET', $a->id_asset, $isNew ? 'Created' : 'Saved');

        // Watch point: hapus yang ditandai, simpan yang berubah.
        foreach (array_keys((array) $r->input('remove', [])) as $wid) {
            $a->watchPoints()->whereKey((int) $wid)->delete();
        }
        foreach ((array) $r->input('watch', []) as $wid => $row) {
            $min = $num($row['normal_min'] ?? null);
            $max = $num($row['normal_max'] ?? null);
            if ($min !== null && $max !== null && $min > $max) {
                throw ValidationException::withMessages(['watch' => t('Normal limit: the minimum cannot be above the maximum.')]);
            }
            $a->watchPoints()->whereKey((int) $wid)->update(['component_code' => ($row['component_code'] ?? '') ?: null, 'check_text' => trim((string) ($row['check_text'] ?? '')) ?: 'Check',
                'method_text' => ($row['method_text'] ?? '') ?: null, 'normal_min' => $min, 'normal_max' => $max, 'measure_uom' => ($row['measure_uom'] ?? '') ?: null,
                'frequency' => array_key_exists($row['frequency'] ?? '', MntWatchPoint::FREQUENCIES) ? $row['frequency'] : 'DAILY',
                'checked_by_role' => array_key_exists($row['checked_by_role'] ?? '', MntWatchPoint::ROLES) ? $row['checked_by_role'] : 'OPERATOR',
                'activate' => filter_var($row['activate'] ?? false, FILTER_VALIDATE_BOOL), 'updated_by' => $login]);
        }
        foreach (array_keys((array) $r->input('remove_parts', [])) as $pid) {
            $a->parts()->whereKey((int) $pid)->delete();
        }
        foreach ((array) $r->input('parts', []) as $pid => $row) {
            $a->parts()->whereKey((int) $pid)->update(['component_code' => ($row['component_code'] ?? '') ?: null, 'qty_per_use' => max(0.001, (float) ($row['qty_per_use'] ?? 1)),
                'min_stock_suggested' => $num($row['min_stock_suggested'] ?? null), 'expected_life_days' => ($row['expected_life_days'] ?? '') === '' ? null : (int) $row['expected_life_days'],
                'is_critical' => filter_var($row['is_critical'] ?? false, FILTER_VALIDATE_BOOL), 'updated_by' => $login]);
        }
        foreach (array_keys((array) $r->input('remove_docs', [])) as $did) {
            $a->documents()->detach((int) $did);
        }

        $msg = 'Profile saved.';
        if ($action === 'add_watch' && trim((string) $r->input('new_watch')) !== '') {
            $a->watchPoints()->create(['check_text' => trim((string) $r->input('new_watch')), 'component_code' => $r->input('new_watch_component') ?: null,
                'frequency' => array_key_exists($r->input('new_watch_frequency'), MntWatchPoint::FREQUENCIES) ? $r->input('new_watch_frequency') : 'DAILY',
                'checked_by_role' => array_key_exists($r->input('new_watch_role'), MntWatchPoint::ROLES) ? $r->input('new_watch_role') : 'OPERATOR',
                'sort_order' => (int) $a->watchPoints()->max('sort_order') + 1, 'activate' => true, 'created_by' => $login, 'updated_by' => $login]);
            $msg = 'Watch point added.';
        } elseif ($action === 'copy_watch' && $r->filled('copy_from')) {
            $n = (new AssetProfileService)->copyWatchPoints(MntAsset::findOrFail($r->integer('copy_from')), $a);
            $msg = tf(':n watch points copied.', ['n' => $n]);
        } elseif ($action === 'add_part' && $r->filled('material_id')) {
            $mat = MatRaw::findOrFail($r->integer('material_id'));
            if (MntAssetPart::where('asset_id', $a->id_asset)->where('material_id', $mat->material_id)->exists()) {
                throw ValidationException::withMessages(['material_id' => t('This spare part is already on the asset.')]);
            }
            MntAssetPart::create(['asset_id' => $a->id_asset, 'material_id' => $mat->material_id, 'qty_per_use' => max(0.001, (float) $r->input('new_part_qty', 1)),
                'component_code' => $r->input('new_part_component') ?: null, 'activate' => true, 'created_by' => $login, 'updated_by' => $login]);
            MntLog::add('ASSET', $a->id_asset, 'Spare part added', $mat->material_code);
            $msg = 'Spare part added.';
        } elseif ($action === 'link_doc' && $r->filled('link_document')) {
            $a->documents()->syncWithoutDetaching([$r->integer('link_document') => ['created_by' => $login, 'updated_by' => $login]]);
            MntLog::add('ASSET', $a->id_asset, 'Document linked', MntDocument::find($r->integer('link_document'))?->doc_no);
            $msg = 'Document linked.';
        }

        return redirect($asset($a->id_asset))->with('v2_flash', $msg);
    })->name('asset.save');

    // ------------------------------------------------------------ SOP and Work Instructions
    Route::post('/document/save', function (Request $r) use ($head, $doc) {
        $head();
        $action = (string) $r->input('action', 'save');
        $m = $r->filled('id') ? MntDocument::findOrFail($r->integer('id')) : null;
        $login = Auth::user()->login;

        if ($m && in_array($action, ['activate', 'deactivate'], true)) {
            $m->update(['activate' => $action === 'activate', 'updated_by' => $login]);
            MntLog::add('DOCUMENT', $m->id_document, $action === 'activate' ? 'Activated' : 'Deactivated');

            return redirect($doc($m->id_document))->with('v2_flash', $action === 'activate' ? 'Document activated.' : 'Document deactivated.');
        }
        if ($m && $action === 'approve') {
            abort_if($m->isApproved(), 422, t('This document is already approved.'));
            $m->update(['status' => 'APPROVED', 'approved_by' => $login, 'approved_at' => now(), 'updated_by' => $login]);
            MntLog::add('DOCUMENT', $m->id_document, 'Approved', 'Revision '.$m->revision);

            return redirect($doc($m->id_document))->with('v2_flash', 'Document approved.');
        }
        if ($m && $action === 'to_draft') {
            abort_unless($m->isApproved(), 422, t('Only an approved document can go back to draft.'));
            $rev = str_pad((string) ((int) $m->revision + 1), 2, '0', STR_PAD_LEFT);
            $m->update(['status' => 'DRAFT', 'revision' => $rev, 'approved_by' => null, 'approved_at' => null, 'updated_by' => $login]);
            MntLog::add('DOCUMENT', $m->id_document, 'Back to draft', 'New revision '.$rev);

            return redirect($doc($m->id_document))->with('v2_flash', 'Document is a draft again (new revision).');
        }
        abort_if($m && $m->isApproved(), 422, t('An approved document is locked. Use Back to Draft first.'));

        $d = $r->validate(['doc_no' => ['required', 'string', 'max:40', 'unique:tm2mnt.mnt_document,doc_no'.($m ? ','.$m->id_document.',id_document' : '')],
            'kind' => ['required', Rule::in(array_keys(MntDocument::KINDS))], 'doc_set' => ['nullable', 'string', 'max:10'], 'title' => ['required', 'string', 'max:200'],
            'file_location' => ['nullable', 'string', 'max:255']], [], ['doc_no' => t('Document no.'), 'title' => t('Title')]);
        $isNew = ! $m;
        $m = $m ? tap($m)->update($d + ['updated_by' => $login]) : MntDocument::create($d + ['revision' => '00', 'status' => 'DRAFT', 'activate' => true, 'created_by' => $login, 'updated_by' => $login]);
        MntLog::add('DOCUMENT', $m->id_document, $isNew ? 'Created' : 'Saved');

        foreach (array_keys((array) $r->input('remove', [])) as $lid) {
            $m->lines()->whereKey((int) $lid)->delete();
        }
        foreach ((array) $r->input('lines', []) as $lid => $row) {
            $m->lines()->whereKey((int) $lid)->update(['section' => trim((string) ($row['section'] ?? '')) ?: 'Steps', 'line_text' => trim((string) ($row['line_text'] ?? '')) ?: '-', 'updated_by' => $login]);
        }
        $msg = 'Document saved.';
        if ($action === 'add_line' && trim((string) $r->input('new_line')) !== '') {
            $m->lines()->create(['seq_no' => (int) $m->lines()->max('seq_no') + 1, 'section' => trim((string) $r->input('new_section', 'Steps')) ?: 'Steps',
                'line_text' => trim((string) $r->input('new_line')), 'created_by' => $login, 'updated_by' => $login]);
            $msg = 'Line added.';
        }

        return redirect($doc($m->id_document))->with('v2_flash', $msg);
    })->name('document.save');

    // ------------------------------------------------------------ Preventive Plan
    $plan = fn (int $id) => route('v2.screen', 'maintenance-plan').'?id='.$id;
    $orderScreen = fn (MntOrder $o) => route('v2.screen', MntOrder::prefixOf($o->order_type) === 'PMO' ? 'maintenance-pmo' : 'maintenance-bmo').'?id='.$o->id_order;

    Route::post('/plan/generate-due', function () use ($head) {
        $head();
        $made = (new PlanService)->generateDue();

        return redirect(route('v2.screen', 'maintenance-pmo'))->with('v2_flash', count($made)
            ? tf(':n preventive order(s) created as Draft. Set the schedule and technician, then Confirm.', ['n' => count($made)])
            : 'No plan is due (or its order already exists).');
    })->name('plan.generate-due');

    Route::post('/plan/save', function (Request $r) use ($head, $plan, $orderScreen) {
        $head();
        $action = (string) $r->input('action', 'save');
        $p = $r->filled('id') ? MntPlan::findOrFail($r->integer('id')) : null;
        $login = Auth::user()->login;
        if ($p && in_array($action, ['activate', 'deactivate'], true)) {
            $p->update(['activate' => $action === 'activate', 'updated_by' => $login]);
            MntLog::add('PLAN', $p->id_plan, $action === 'activate' ? 'Activated' : 'Deactivated');

            return redirect($plan($p->id_plan))->with('v2_flash', $action === 'activate' ? 'Plan activated.' : 'Plan deactivated. It no longer creates orders.');
        }
        $d = $r->validate([
            'plan_code' => ['required', 'string', 'max:40', 'unique:tm2mnt.mnt_plan,plan_code'.($p ? ','.$p->id_plan.',id_plan' : '')],
            'plan_name' => ['required', 'string', 'max:150'], 'asset_id' => ['required', 'integer', 'exists:tm2mnt.mnt_asset,id_asset'],
            'order_type' => ['required', Rule::in(array_keys(MntPlan::TYPES))], 'trigger_mode' => ['required', Rule::in(array_keys(MntPlan::TRIGGERS))],
            'interval_value' => ['nullable', 'integer', 'min:1', 'required_unless:trigger_mode,METER'], 'interval_unit' => ['nullable', Rule::in(array_keys(MntPlan::UNITS)), 'required_unless:trigger_mode,METER'],
            'meter_type' => ['nullable', Rule::in(array_keys(MntPlan::METERS)), 'required_unless:trigger_mode,TIME'], 'meter_interval' => ['nullable', 'numeric', 'min:1', 'required_unless:trigger_mode,TIME'],
            'schedule_mode' => ['required', Rule::in(array_keys(MntPlan::MODES))], 'lead_days' => ['nullable', 'integer', 'min:0', 'max:60'], 'est_minutes' => ['nullable', 'integer', 'min:0'],
            'last_done_date' => ['nullable', 'date'], 'last_done_meter' => ['nullable', 'numeric', 'min:0'], 'next_due_date' => ['nullable', 'date'],
            'certificate_valid_until' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['plan_code' => t('Plan code'), 'plan_name' => t('Plan name'), 'asset_id' => t('Asset'), 'interval_value' => t('Every'), 'meter_interval' => t('Or every (meter)')]);
        $d['needs_stop'] = $r->boolean('needs_stop');
        $d['lead_days'] = (int) ($d['lead_days'] ?? 3);
        $isNew = ! $p;
        $p = $p ? tap($p)->update($d + ['updated_by' => $login]) : MntPlan::create($d + ['activate' => true, 'created_by' => $login, 'updated_by' => $login]);
        MntLog::add('PLAN', $p->id_plan, $isNew ? 'Created' : 'Saved');

        foreach (array_keys((array) $r->input('remove', [])) as $tid) {
            $p->tasks()->whereKey((int) $tid)->delete();
        }
        foreach ((array) $r->input('tasks', []) as $tid => $row) {
            $min = ($row['min_value'] ?? '') === '' ? null : (float) $row['min_value'];
            $max = ($row['max_value'] ?? '') === '' ? null : (float) $row['max_value'];
            if ($min !== null && $max !== null && $min > $max) {
                throw ValidationException::withMessages(['tasks' => t('Normal limit: the minimum cannot be above the maximum.')]);
            }
            $p->tasks()->whereKey((int) $tid)->update(['task_text' => $row['task_text'] ?? '', 'task_kind' => array_key_exists($row['task_kind'] ?? '', MntPlanTask::KINDS) ? $row['task_kind'] : 'INSPECT',
                'measure_uom' => ($row['measure_uom'] ?? '') ?: null, 'min_value' => $min, 'max_value' => $max, 'is_required' => filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOL), 'updated_by' => $login]);
        }
        foreach (array_keys((array) $r->input('remove_parts', [])) as $pid) {
            $p->parts()->whereKey((int) $pid)->delete();
        }
        foreach ((array) $r->input('parts', []) as $pid => $row) {
            $p->parts()->whereKey((int) $pid)->update(['qty' => max(0.001, (float) ($row['qty'] ?? 1)), 'updated_by' => $login]);
        }
        $msg = 'Plan saved.';
        if ($action === 'add_task' && trim((string) $r->input('new_task')) !== '') {
            $p->tasks()->create(['seq_no' => (int) $p->tasks()->max('seq_no') + 1, 'task_text' => trim((string) $r->input('new_task')),
                'task_kind' => array_key_exists($r->input('new_task_kind'), MntPlanTask::KINDS) ? $r->input('new_task_kind') : 'INSPECT', 'is_required' => true, 'created_by' => $login, 'updated_by' => $login]);
            $msg = 'Task added.';
        } elseif ($action === 'add_part' && $r->filled('material_id')) {
            $mat = MatRaw::findOrFail($r->integer('material_id'));
            MntPlanPart::create(['plan_id' => $p->id_plan, 'material_id' => $mat->material_id, 'qty' => max(0.001, (float) $r->input('new_part_qty', 1)),
                'uom_code' => $mat->uom_stock ?: $mat->uom_purchase, 'created_by' => $login, 'updated_by' => $login]);
            $msg = 'Spare part added.';
        }
        // Jatuh tempo berikutnya selalu dihitung sistem (bukan diketik), kecuali FIXED yang tanggal pertamanya diisi user.
        $p->refresh();
        $svc = new PlanService($login);
        if (! ($p->schedule_mode === 'FIXED' && $r->filled('next_due_date'))) {
            $p->update($svc->nextDue($p, $p->last_done_date ? CarbonImmutable::parse($p->last_done_date) : null, $p->last_done_meter));
        }
        if ($action === 'generate') {
            $o = $svc->generateFromPlan($p->refresh()->load(['tasks', 'parts']), true);
            if ($o) {
                return redirect($orderScreen($o))->with('v2_flash', 'Preventive order created (Draft) from this plan.');
            }
            $msg = 'This plan still has an open order or is inactive. No new order was created.';
        }

        return redirect($plan($p->id_plan))->with('v2_flash', $msg);
    })->name('plan.save');

    // ------------------------------------------------------------ Maintenance Order (PMO, BMO, IMO)
    Route::post('/order/create', function (Request $r) use ($head, $orderScreen) {
        $head();
        $d = $r->validate(['asset_id' => ['required', 'integer', 'exists:tm2mnt.mnt_asset,id_asset'], 'order_type' => ['required', Rule::in(array_keys(MntOrder::TYPES))],
            'description' => ['required', 'string', 'max:255']], [], ['asset_id' => t('Asset'), 'description' => t('Description')]);
        $o = (new OrderService)->create($d);

        return redirect($orderScreen($o))->with('v2_flash', 'Draft order created. Complete it, then Confirm.');
    })->name('order.create');

    Route::post('/order/{id}/save', function (Request $r, int $id) use ($head, $orderScreen) {
        $o = MntOrder::findOrFail($id);
        $action = (string) $r->input('action', 'save');
        $s = new OrderService;
        $r->validate(['planned_start' => ['nullable', 'date'], 'planned_end' => ['nullable', 'date'], 'cost_vendor' => ['nullable', 'numeric', 'min:0'],
            'meter_at_done' => ['nullable', 'numeric', 'min:0'], 'asset_id' => ['nullable', 'integer', 'exists:tm2mnt.mnt_asset,id_asset']]);
        switch ($action) {
            case 'delete':
                $head();
                $s->deleteDraft($o);

                return redirect(route('v2.screen', MntOrder::prefixOf($o->order_type) === 'PMO' ? 'maintenance-pmo' : 'maintenance-bmo'))->with('v2_flash', 'Draft order deleted.');
            case 'cancel':
                $head();
                $s->cancel($o, (string) $r->input('_reason', ''));

                return redirect($orderScreen($o))->with('v2_flash', 'Order cancelled.');
            case 'draft':
                $head();
                $s->backToDraft($o);

                return redirect($orderScreen($o))->with('v2_flash', 'Order is back to Draft.');
            case 'start':
                $s->start($o);

                return redirect($orderScreen($o))->with('v2_flash', 'Order started (In Progress). Downtime and machine status are recorded for machines.');
            case 'close':
                $head();
                $s->close($o);

                return redirect($orderScreen($o))->with('v2_flash', 'Order closed. Cost is final and the next preventive due date is calculated.');
        }
        if ($o->docStatus() === 'DRAFT') {
            $head();
        }
        if ($o->docStatus() === 'DRAFT' || $o->isWorking()) {
            $s->save($o, $r->all());
        }
        $o->refresh();
        switch ($action) {
            case 'add_task':
                $s->addTask($o, (string) $r->input('new_task'));
                $msg = 'Task added.';
                break;
            case 'add_part':
                abort_unless($r->filled('material_id'), 422, t('Choose a spare part from the material master.'));
                $s->addPart($o, $r->integer('material_id'), max(0.001, (float) $r->input('new_part_qty', 1)));
                $msg = 'Spare part added.';
                break;
            case 'add_labor':
                abort_unless($r->filled('labor_employee_id'), 422, t('Choose a technician.'));
                $s->addLabor($o, $r->integer('labor_employee_id'), $r->integer('labor_minutes'), $r->input('labor_date') ?: null);
                $msg = 'Labor added.';
                break;
            case 'confirm':
                $head();
                $s->confirm($o);
                $msg = tf('Order confirmed: :no (Scheduled).', ['no' => $o->refresh()->doc_no]);
                break;
            case 'hold':
                $s->hold($o, (string) $r->input('_reason', ''));
                $msg = 'Order on hold (Waiting Part).';
                break;
            case 'resume':
                $s->resume($o);
                $msg = 'Order resumed (In Progress).';
                break;
            case 'done':
                $s->done($o);
                $msg = 'Order done. Downtime closed and the machine is Ready again. Waiting for Close by the head of maintenance.';
                break;
            default:
                $msg = 'Order saved.';
        }

        return redirect($orderScreen($o))->with('v2_flash', $msg);
    })->name('order.save');

    // ------------------------------------------------------------ Daily Check
    Route::post('/daily/open', function (Request $r) {
        $d = $r->validate(['asset_id' => ['required', 'integer', 'exists:tm2mnt.mnt_asset,id_asset'], 'check_date' => ['required', 'date'], 'shift_code' => ['required', Rule::in(array_keys(MntDailyCheck::SHIFTS))]],
            [], ['asset_id' => t('Asset'), 'check_date' => t('Date'), 'shift_code' => t('Shift')]);
        $sheet = (new DailyCheckService)->open(MntAsset::findOrFail($d['asset_id']), $d['check_date'], $d['shift_code']);

        return redirect(route('v2.screen', 'maintenance-daily-check').'?id='.$sheet->id_daily_check)->with('v2_flash', 'Check sheet opened.');
    })->name('daily.open');

    Route::post('/daily/{id}/save', function (Request $r, int $id) {
        $sheet = MntDailyCheck::findOrFail($id);
        $svc = new DailyCheckService;
        $svc->save($sheet, (array) $r->input('lines', []));
        $msg = 'Check sheet saved.';
        if ($r->input('action') === 'submit') {
            $n = $svc->submit($sheet->refresh());
            $msg = $n ? tf('Check sheet submitted. :n request(s) opened for the head of maintenance.', ['n' => $n]) : 'Check sheet submitted. Everything is OK.';
        }

        return redirect(route('v2.screen', 'maintenance-daily-check').'?id='.$id)->with('v2_flash', $msg);
    })->name('daily.save');
});
