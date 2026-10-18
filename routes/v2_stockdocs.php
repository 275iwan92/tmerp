<?php

/*
 * REMARK
 * Tujuan       : Route layar dokumen stok manual v2 (form dokumen Inventory): Stock In dulu (inv-stock-in-form), disusul Stock Out / Return / Adjustment.
 * Sumber hulu  : App\Services\Inventory\StockInEntryService (simpan Draft, Confirm = posting), StockReversalService (Cancel dengan dokumen pembalik, Duplicate), StockDocumentService.
 * Input        : POST form dokumen (stock_class, id, action save / confirm / cancel / duplicate) dan POST dialog Cancel Document (alasan).
 * Output       : Redirect ke form (id dokumen) atau ke grid kelasnya dengan flash v2_flash / errors.
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris web.php yang dikutip kartu TG tidak bergeser); layar inv-stock-in-form.
 * Status       : Aktif. Dibuat 2026-10-19 (Master T5, Jo): menggantikan halaman v1 pages::inventory.stock-in.
 */

use App\Services\Inventory\StockInEntryService;
use App\Services\Inventory\StockReversalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Stock In (RM, FG, SFG): Save = Draft, Confirm = simpan lalu posting ke Kartu Stok, Cancel = batalkan Draft, Duplicate = salinan Draft.
Route::prefix('v2/inv/stock-in')->name('v2.inv.stockin.')->middleware('module:INV')->group(function () {
    $form = fn (string $class, int $id) => redirect(route('v2.screen', 'inv-stock-in-form').'?class='.$class.'&id='.$id);
    $grid = fn (string $class) => redirect(route('v2.screen', 'inv-'.strtolower($class).'-stock-in'));

    Route::post('/save', function (Request $r, StockInEntryService $svc) use ($form, $grid) {
        $class = strtoupper((string) $r->input('stock_class'));
        abort_unless(isset(StockInEntryService::CLASSES[$class]), 404);
        $id = $r->filled('id') ? (int) $r->input('id') : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();

        if ($id && $action === 'cancel') {
            $svc->cancelDraft($class, $id, $user);

            return $grid($class)->with('v2_flash', t('Draft cancelled.'));
        }
        if ($id && $action === 'duplicate') {
            $copy = app(StockReversalService::class)->duplicate($class, $id, $user->login);
            abort_unless($copy, 422);

            return $form($class, $copy)->with('v2_flash', t('Draft copy created. Correct it, then Confirm.'));
        }

        $id = $svc->save($class, $id, $r->only(['doc_date', 'doc_cat', 'warehouse_id', 'notes']), (array) $r->input('lines'), $user);
        if ($action === 'confirm') {
            $res = $svc->post($class, $id, $user);
            if (! $res['posted']) {
                // Draft sudah tersimpan; alasan penolakan (periode tertutup, saldo minus) tampil di form.
                return $form($class, $id)->withErrors(['document' => $res['error']]);
            }

            return $form($class, $id)->with('v2_flash', t('Document posted to the Stock Card.').(isset($res['stock_warning']) ? ' '.$res['stock_warning'] : ''));
        }

        return $form($class, $id)->with('v2_flash', t('Draft saved.'));
    })->name('save');

    Route::post('/{class}/{id}/reverse', function (Request $r, string $class, int $id) use ($form) {
        $class = strtoupper($class);
        abort_unless(isset(StockInEntryService::CLASSES[$class]), 404);
        $res = app(StockReversalService::class)->reverse($class, $id, (string) $r->input('reason'), Auth::user()->login);
        if (! $res['ok']) {
            return $form($class, $id)->withErrors(['document' => $res['error']]);
        }

        return $form($class, $id)->with('v2_flash', tf('Document cancelled. Reversal :no posted.', ['no' => $res['doc_no']]).' '.($res['stock_warning'] ?? ''));
    })->whereNumber('id')->name('reverse');
});
