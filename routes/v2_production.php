<?php

/*
 * REMARK
 * Tujuan       : Route layar master modul Production v2: Scrap Type (prod-scrap-form), Routing Template (prod-routing-form) dan BOP Rule (prod-bop-form), disusul Process dan Machine.
 * Sumber hulu  : App\Services\Production\ScrapTypeService, RoutingTemplateEntryService dan BopRuleEntryService (dan layanan master Production berikutnya).
 * Input        : POST form (id, action save / activate / deactivate, isian; Routing: juga baris langkah steps[]).
 * Output       : Redirect ke form (id) dengan flash v2_flash / errors.
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris web.php yang dikutip kartu TG tidak bergeser); layar prod-scrap-form, prod-routing-form dan prod-bop-form.
 * Status       : Aktif. Dibuat 2026-10-19 (Master T6b, Jo): menggantikan halaman v1 pages::production.scrap-type, routing-template dan bop-rule.
 */

use App\Models\Master\ProdBopRule;
use App\Models\Master\ProdRoutingTemplate;
use App\Models\Prd\PrdScrapType;
use App\Services\Production\BopRuleEntryService;
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
