<?php

/*
 * REMARK
 * Tujuan       : Route layar master modul Production v2: Scrap Type (prod-scrap-form) dan Routing Template (prod-routing-form), disusul BOP Rule, Process dan Machine.
 * Sumber hulu  : App\Services\Production\ScrapTypeService dan RoutingTemplateEntryService (dan layanan master Production berikutnya).
 * Input        : POST form (id, action save / activate / deactivate, isian; Routing: juga baris langkah steps[]).
 * Output       : Redirect ke form (id) dengan flash v2_flash / errors.
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris web.php yang dikutip kartu TG tidak bergeser); layar prod-scrap-form dan prod-routing-form.
 * Status       : Aktif. Dibuat 2026-10-19 (Master T6b, Jo): menggantikan halaman v1 pages::production.scrap-type dan routing-template.
 */

use App\Models\Master\ProdRoutingTemplate;
use App\Models\Prd\PrdScrapType;
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
