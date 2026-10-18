<?php

/*
 * REMARK
 * Tujuan       : Route layar dokumen stok manual v2 (form dokumen Inventory): Stock In, Stock Out dan Return from Production (inv-stock-doc-form), disusul Adjustment.
 * Sumber hulu  : App\Services\Inventory\StockDocEntryService (simpan Draft, Confirm = posting), StockReversalService (Cancel dengan dokumen pembalik, Duplicate), StockDocumentService.
 * Input        : POST form dokumen (stock_kind, stock_class, id, action save / confirm / cancel / duplicate) dan POST dialog Cancel Document (alasan).
 * Output       : Redirect ke form (id dokumen) atau ke grid jenis dan kelasnya dengan flash v2_flash / errors.
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris web.php yang dikutip kartu TG tidak bergeser); layar inv-stock-doc-form.
 * Status       : Aktif. Dibuat 2026-10-19 (Master T5, Jo): menggantikan halaman v1 pages::inventory.stock-in dan stock-out.
 */

use App\Services\Inventory\StockDocEntryService;
use App\Services\Inventory\StockReversalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Save = Draft, Confirm = simpan lalu posting ke Kartu Stok, Cancel = batalkan Draft, Duplicate = salinan Draft; dialog Cancel Document = dokumen pembalik.
Route::prefix('v2/inv/stock-doc')->name('v2.inv.stockdoc.')->middleware('module:INV')->group(function () {
    $form = fn (string $kind, string $class, int $id) => redirect(route('v2.screen', 'inv-stock-doc-form').'?kind='.$kind.'&class='.$class.'&id='.$id);
    $grid = fn (string $kind, string $class) => redirect(route('v2.screen', 'inv-'.strtolower($class).'-stock-'.$kind));

    // Return baru: kartu awal memilih Stock Out sumber, lalu form terbuka dengan baris dari sumber itu.
    Route::post('/{kind}/{class}/start', function (Request $r, string $kind, string $class) {
        $class = strtoupper($class);
        abort_unless($kind === 'return' && StockDocEntryService::supports($kind, $class), 404);
        $source = (int) $r->input('source');
        if (! app(StockDocEntryService::class)->returnableSource($class, $source)) {
            return back()->withErrors(['source' => t('Choose a posted Stock Out that still has a returnable balance.')]);
        }

        return redirect(route('v2.screen', 'inv-stock-doc-form').'?kind=return&class='.$class.'&new=1&source='.$source);
    })->name('start');

    Route::post('/save', function (Request $r, StockDocEntryService $svc) use ($form, $grid) {
        $kind = (string) $r->input('stock_kind');
        $class = strtoupper((string) $r->input('stock_class'));
        abort_unless(StockDocEntryService::supports($kind, $class), 404);
        $id = $r->filled('id') ? (int) $r->input('id') : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();

        if ($id && $action === 'cancel') {
            $svc->cancelDraft($class, $id, $user);

            return $grid($kind, $class)->with('v2_flash', t('Draft cancelled.'));
        }
        if ($id && $action === 'duplicate') {
            $copy = app(StockReversalService::class)->duplicate($class, $id, $user->login);
            abort_unless($copy, 422);

            return $form($kind, $class, $copy)->with('v2_flash', t('Draft copy created. Correct it, then Confirm.'));
        }

        $id = $svc->save($kind, $class, $id, $r->only(['doc_date', 'doc_cat', 'warehouse_id', 'issued_to_dept', 'process_code', 'notes', 'source_doc_id']), (array) $r->input('lines'), $user);
        if ($action === 'confirm') {
            $res = $svc->post($class, $id, $user);
            if (! $res['posted']) {
                // Draft sudah tersimpan; alasan penolakan (periode tertutup, saldo minus) tampil di form.
                return $form($kind, $class, $id)->withErrors(['document' => $res['error']]);
            }

            return $form($kind, $class, $id)->with('v2_flash', t('Document posted to the Stock Card.').' '.($res['stock_warning'] ?? '').' '.($res['journal_warning'] ?? ''));
        }

        return $form($kind, $class, $id)->with('v2_flash', t('Draft saved.'));
    })->name('save');

    Route::post('/{kind}/{class}/{id}/reverse', function (Request $r, string $kind, string $class, int $id) use ($form) {
        $class = strtoupper($class);
        abort_unless(StockDocEntryService::supports($kind, $class), 404);
        $res = app(StockReversalService::class)->reverse($class, $id, (string) $r->input('reason'), Auth::user()->login);
        if (! $res['ok']) {
            return $form($kind, $class, $id)->withErrors(['document' => $res['error']]);
        }

        return $form($kind, $class, $id)->with('v2_flash', tf('Document cancelled. Reversal :no posted.', ['no' => $res['doc_no']]).' '.($res['stock_warning'] ?? '').' '.($res['journal_warning'] ?? ''));
    })->whereNumber('id')->name('reverse');
});
