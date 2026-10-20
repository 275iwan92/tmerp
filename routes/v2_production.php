<?php

/*
 * REMARK
 * Tujuan       : Route layar master modul Production v2: Scrap Type (prod-scrap-form), Routing Template (prod-routing-form), BOP Rule (prod-bop-form), Process (prod-process-form) dan Machine (prod-machine-form).
 * Sumber hulu  : App\Services\Production\ScrapTypeService, RoutingTemplateEntryService, BopRuleEntryService dan ProcessMasterService dan MachineMasterService (dan layanan master Production berikutnya).
 * Input        : POST form (id, action save / activate / deactivate, isian; Routing: juga baris langkah steps[]; Process: juga baris matriks mesin machines[]; Machine: juga baris processes[] dan downtimes[]).
 * Output       : Redirect ke form (id) dengan flash v2_flash / errors.
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris web.php yang dikutip kartu TG tidak bergeser); layar prod-scrap-form, prod-routing-form, prod-bop-form, prod-process-form dan prod-machine-form.
 * Status       : Aktif. Dibuat 2026-10-19 (Master T6b, Jo): menggantikan halaman v1 pages::production.scrap-type, routing-template, bop-rule, master-process dan master.machine.
 */

use App\Models\Master\ProdBopRule;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdRoutingTemplate;
use App\Models\Prd\PrdScrapType;
use App\Services\Production\BopRuleEntryService;
use App\Services\Production\MachineMasterService;
use App\Services\Production\ProcessMasterService;
use App\Services\Production\RoutingTemplateEntryService;
use App\Services\Production\ScrapTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Scrap Type: save / activate / deactivate. Aturan = ScrapTypeService (dipindah dari v1 pages::production.scrap-type).
Route::prefix('v2/prod/scrap')->name('v2.prod.scrap.')->middleware('module:PROD')->group(function () {
    Route::post('/save', function (Request $r, ScrapTypeService $svc) {
        $t = $r->filled('id') ? PrdScrapType::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();
        $form = fn (PrdScrapType $x) => redirect(route('v2.screen', 'prod-scrap-form').'?id='.$x->type_id);

        if ($t && in_array($action, ['activate', 'deactivate'], true)) {
            $svc->setActive($t, $action === 'activate', $user);

            return $form($t)->with('v2_flash', tf($action === 'activate' ? ':name activated.' : ':name deactivated.', ['name' => $t->type_name]));
        }
        $t = $svc->save($t, $r->except(['id', 'action']), $user);

        return $form($t)->with('v2_flash', tf(':name saved.', ['name' => $t->type_name]));
    })->name('save');
});

// Routing Template: save (header + semua langkah) / activate / deactivate. Aturan = RoutingTemplateEntryService (dipindah dari v1 pages::production.routing-template).
Route::prefix('v2/prod/routing')->name('v2.prod.routing.')->middleware('module:PROD')->group(function () {
    Route::post('/save', function (Request $r, RoutingTemplateEntryService $svc) {
        $t = $r->filled('id') ? ProdRoutingTemplate::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();
        $form = fn (ProdRoutingTemplate $x) => redirect(route('v2.screen', 'prod-routing-form').'?id='.$x->template_id);

        if ($t && in_array($action, ['activate', 'deactivate'], true)) {
            $svc->setActive($t, $action === 'activate', $user);

            return $form($t)->with('v2_flash', tf($action === 'activate' ? ':name activated.' : ':name deactivated.', ['name' => $t->template_name]));
        }
        $t = $svc->save($t, $r->except(['id', 'action', 'steps']), (array) $r->input('steps'), $user);

        return $form($t)->with('v2_flash', tf(':name saved.', ['name' => $t->template_name]));
    })->name('save');
});

// BOP Rule: save / activate / deactivate / apply suggestion / refresh suggestions (hanya admin). Aturan = BopRuleEntryService (dipindah dari v1 pages::production.bop-rule).
Route::prefix('v2/prod/bop')->name('v2.prod.bop.')->middleware('module:PROD')->group(function () {
    Route::post('/save', function (Request $r, BopRuleEntryService $svc) {
        $rule = $r->filled('id') ? ProdBopRule::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();
        $form = fn (ProdBopRule $x) => redirect(route('v2.screen', 'prod-bop-form').'?id='.$x->id_bop_rule);
        $name = fn (ProdBopRule $x) => $x->step_label ?: ($x->material_role ?: 'Rule '.$x->id_bop_rule);

        if ($action === 'refresh') {
            $msg = $svc->refreshSuggestions($user);

            return ($rule ? $form($rule) : redirect(route('v2.screen', 'prod-bop-rule')))->with('v2_flash', $msg);
        }
        if ($rule && $action === 'apply') {
            return $form($rule)->with('v2_flash', t($svc->applySuggestion($rule, $user)));
        }
        if ($rule && in_array($action, ['activate', 'deactivate'], true)) {
            $svc->setActive($rule, $action === 'activate', $user);

            return $form($rule)->with('v2_flash', tf($action === 'activate' ? ':name activated.' : ':name deactivated.', ['name' => $name($rule)]));
        }
        $rule = $svc->save($rule, $r->except(['id', 'action']), $user);

        return $form($rule)->with('v2_flash', tf(':name saved.', ['name' => $name($rule)]));
    })->name('save');
});

// Process: save (data proses + matriks mesin) / activate / deactivate. DATA REAL: aturan = ProcessMasterService + ProcessMachineMatrixService (dipindah dari v1 pages::production.master-process).
Route::prefix('v2/prod/process')->name('v2.prod.process.')->middleware('module:PROD')->group(function () {
    Route::post('/save', function (Request $r, ProcessMasterService $svc) {
        $p = $r->filled('id') ? ProdProcess::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();
        $form = fn (ProdProcess $x) => redirect(route('v2.screen', 'prod-process-form').'?id='.$x->id_process);

        if ($p && in_array($action, ['activate', 'deactivate'], true)) {
            $svc->setActive($p, $action === 'activate', $user);

            return $form($p)->with('v2_flash', tf($action === 'activate' ? ':name activated.' : ':name deactivated.', ['name' => $p->process_name]));
        }
        $p = $svc->save($p, $r->except(['id', 'action', 'machines']), (array) $r->input('machines'), $user);

        return $form($p)->with('v2_flash', tf(':name saved.', ['name' => $p->process_name]));
    })->name('save');
});

// Machine: save (data mesin + matriks proses + log downtime) / activate / deactivate. DATA REAL: aturan = MachineMasterService + ProcessMachineMatrixService (dipindah dari v1 pages::master.machine).
Route::prefix('v2/prod/machine')->name('v2.prod.machine.')->middleware('module:PROD')->group(function () {
    Route::post('/save', function (Request $r, MachineMasterService $svc) {
        $m = $r->filled('id') ? ProdMachine::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();
        $form = fn (ProdMachine $x) => redirect(route('v2.screen', 'prod-machine-form').'?id='.$x->machine_id);

        if ($m && in_array($action, ['activate', 'deactivate'], true)) {
            $svc->setActive($m, $action === 'activate', $user);

            return $form($m)->with('v2_flash', tf($action === 'activate' ? ':name activated.' : ':name deactivated.', ['name' => $m->machine_code]));
        }
        $m = $svc->save($m, $r->except(['id', 'action', 'processes', 'downtimes']), (array) $r->input('processes'), (array) $r->input('downtimes'), $user);

        return $form($m)->with('v2_flash', tf(':name saved.', ['name' => $m->machine_code]));
    })->name('save');
});

// Cetak Work Order untuk lapangan (formulir kerja, lembar berbagian x-dl::print-sheet). Isi = WorkOrderPrint (dipindah dari v1 pages::production.wo-print).
Route::get('v2/production/wo/{wo}/print', function (int $wo) {
    \App\Support\V2\Print\PrintDocs::boot();

    return view('v2.print-wo', ['data' => new \App\Support\V2\Data\WorkOrderPrint($wo), 'back' => route('v2.screen', 'production-wo').'?id='.$wo]);
})->middleware('module:PROD')->whereNumber('wo')->name('v2.production.wo.print');

// Eksekusi Work Order di layar production-wo: gerbang QC, breakdown (mesin alternatif / PO subkontrak), terima dari subkontrak, dan substitusi bahan di Bill of Material. Aturan = WoQcGateService, WoBreakdownService, WoMaterialSubstitutionService (dipindah dari v1 pages::production.wo-entry).
// Tiap aksi kembali ke layar WO (tab asal) dengan pesan sukses v2_flash atau galat (ValidationException / larangan peran).
Route::prefix('v2/production/wo/{wo}')->name('v2.production.wo.')->middleware('module:PROD')->whereNumber('wo')->group(function () {
    $run = function (int $wo, string $tab, string|\Closure $ok, \Closure $fn, array $extra = []) {
        $back = redirect(route('v2.screen', 'production-wo').'?id='.$wo.'&tab='.$tab.($extra ? '&'.http_build_query($extra) : ''));
        try {
            $result = $fn();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $back->withInput()->withErrors($e->errors());
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $back->withErrors(['document' => t($e->getMessage())]);
        }

        return $back->with('v2_flash', $ok instanceof \Closure ? $ok($result) : $ok);
    };
    $operation = fn (int $wo, int $op) => \App\Models\Prd\PrdWoOperation::where('wo_id', $wo)->findOrFail($op);
    $order = fn (int $wo) => \App\Models\Prd\PrdWo::findOrFail($wo);
    $me = fn () => \Illuminate\Support\Facades\Auth::user();

    // Gerbang QC (hanya QA) dan terima kembali dari subkontrak.
    Route::post('/op/{op}/qc-pass', fn (int $wo, int $op) => $run($wo, 'bop', t('Task passed QC.'), fn () => app(\App\Services\Production\WoQcGateService::class)->pass($operation($wo, $op)->operation_id, $me())))->name('op.qc-pass');
    Route::post('/op/{op}/qc-reject', fn (Request $r, int $wo, int $op) => $run($wo, 'bop', t('Task rejected by QC.'), fn () => app(\App\Services\Production\WoQcGateService::class)->reject($operation($wo, $op)->operation_id, (string) $r->input('reason'), $me())))->name('op.qc-reject');
    Route::post('/op/{op}/rework', fn (int $wo, int $op) => $run($wo, 'bop', t('Task returned for rework.'), fn () => app(\App\Services\Production\WoQcGateService::class)->rework($operation($wo, $op)->operation_id)))->name('op.rework');
    Route::post('/op/{op}/receive-subcontract', fn (int $wo, int $op) => $run($wo, 'bop', t('Task is back from subcontract and must pass QC.'), fn () => app(\App\Services\Production\WoQcGateService::class)->receiveFromSubcontract($operation($wo, $op)->operation_id)))->name('op.receive-subcontract');

    // Breakdown: lapor, lalu pindah ke mesin alternatif atau PO subkontrak (Manager Operasional ke atas).
    Route::post('/op/{op}/breakdown', fn (Request $r, int $wo, int $op) => $run($wo, 'bop', t('Breakdown reported: choose to re-route to another machine or subcontract.'), fn () => app(\App\Services\Production\WoBreakdownService::class)->report($operation($wo, $op)->operation_id, (int) $r->input('downtime'), $me())))->name('op.breakdown');
    Route::post('/op/{op}/reroute', fn (Request $r, int $wo, int $op) => $run($wo, 'bop', t('Task moved to the alternative machine and is back in the queue.'), fn () => app(\App\Services\Production\WoBreakdownService::class)->rerouteToMachine($operation($wo, $op)->operation_id, (int) $r->input('alt_machine_id'), $me())))->name('op.reroute');
    Route::post('/op/{op}/subcontract', fn (Request $r, int $wo, int $op) => $run($wo, 'bop', fn ($po) => tf('Subcontract PO :no issued.', ['no' => $po->doc_no]),
        fn () => app(\App\Services\Production\WoBreakdownService::class)->createSubcontractPo($operation($wo, $op)->operation_id, (int) $r->input('vendor_id'), (float) \Devlib\Locale::parse($r->input('estimated_cost')), (string) $r->input('due_out'), (string) $r->input('due_return'), $me())))->name('op.subcontract');

    // Substitusi bahan: ajukan (pratinjau lewat GET ke layar), Approve / Reject / Undo oleh Manager Operasional ke atas.
    Route::post('/bom/{line}/substitute', fn (Request $r, int $wo, int $line) => $run($wo, 'bom', t('Substitution sent, waiting for Operations Manager approval or above.'), fn () => app(\App\Services\Production\WoMaterialSubstitutionService::class)->submit($order($wo), $line, (int) $r->input('rawmat_id'), (float) \Devlib\Locale::parse($r->input('length_mm')), (float) \Devlib\Locale::parse($r->input('width_mm')), $r->input('reason'), $me())))->name('bom.substitute');
    Route::post('/sub/{sub}/approve', fn (int $wo, int $sub) => $run($wo, 'bom', t('Substitution approved and active.'), fn () => app(\App\Services\Production\WoMaterialSubstitutionService::class)->approve($sub, $me())))->name('sub.approve');
    Route::post('/sub/{sub}/reject', fn (Request $r, int $wo, int $sub) => $run($wo, 'bom', t('Substitution rejected.'), fn () => app(\App\Services\Production\WoMaterialSubstitutionService::class)->reject($sub, (string) $r->input('reason'), $me())))->name('sub.reject');
    Route::post('/sub/{sub}/undo', fn (int $wo, int $sub) => $run($wo, 'bom', t('Substitution cancelled, back to the original material.'), fn () => app(\App\Services\Production\WoMaterialSubstitutionService::class)->undo($sub, $me())))->name('sub.undo');
});
