<?php

/*
 * REMARK
 * Tujuan       : Route layar Purchase Return v2 (pur-return-form): kartu Start (pilih sumber), Save Draft, Submit ke approval, Duplicate, Cancel Draft dengan alasan, Mark Complete.
 * Sumber hulu  : App\Services\Pur\PurchaseReturnService (simpan, submit, cancel, complete, duplicate).
 * Input        : POST kartu Start (source, pv_id atau supplier_id), POST form (id, action save / submit / duplicate, isian header dan baris), POST dialog Cancel (cancel_reason), POST Mark Complete.
 * Output       : Redirect ke form (id dokumen) atau ke grid purchase-return dengan flash v2_flash / errors.
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris web.php yang dikutip kartu TG tidak bergeser); layar pur-return-form.
 * Status       : Aktif. Dibuat 2026-10-19 (Master T6, Jo): menggantikan halaman v1 pages::pur.prt.index dan pur.prt.entry. Keputusan Approve / Reject / Hold tetap di Approval Center (ApprovalCenter::decide memakai PurchaseReturnService).
 */

use App\Services\Pur\PurchaseReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::prefix('v2/pur/return')->name('v2.pur.return.')->middleware('module:PROC')->group(function () {
    $form = fn (int $id) => redirect(route('v2.screen', 'pur-return-form').'?id='.$id);

    Route::post('/start', function (Request $r, PurchaseReturnService $svc) {
        $source = (string) $r->input('source');
        abort_unless(isset(PurchaseReturnService::SOURCES[$source]), 422);
        $key = $source === 'QC_REJECT' ? 'pv_id' : 'supplier_id';
        $val = (int) $r->input($key);
        $ok = $source === 'QC_REJECT' ? collect($svc->returnableReceipts())->contains('id', $val) : isset($svc->supplierOptions()[$val]);
        if (! $ok) {
            return back()->withErrors([$key => t($source === 'QC_REJECT' ? 'Choose a posted receipt with QC-rejected goods.' : 'Choose an active supplier.')]);
        }

        return redirect(route('v2.screen', 'pur-return-form').'?new=1&source='.$source.'&'.$key.'='.$val);
    })->name('start');

    Route::post('/save', function (Request $r, PurchaseReturnService $svc) use ($form) {
        $id = $r->filled('id') ? (int) $r->input('id') : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();

        if ($id && $action === 'duplicate') {
            return $form($svc->duplicate($id, $user))->with('v2_flash', t('Draft copy created. Correct it, then Submit.'));
        }
        $id = $svc->save($id, $r->except(['_token', 'lines', 'action', 'id']), (array) $r->input('lines'), $user);
        if ($action === 'submit') {
            $warn = $svc->submit($id, $user);

            return $form($id)->with('v2_flash', t('Submitted for approval.').' '.$warn);
        }

        return $form($id)->with('v2_flash', t('Draft saved.'));
    })->name('save');

    Route::post('/{id}/cancel', function (Request $r, int $id, PurchaseReturnService $svc) {
        $svc->cancel($id, (string) $r->input('cancel_reason'), Auth::user());

        return redirect(route('v2.screen', 'purchase-return'))->with('v2_flash', t('Return cancelled.'));
    })->whereNumber('id')->name('cancel');

    Route::post('/{id}/complete', function (int $id, PurchaseReturnService $svc) use ($form) {
        $svc->complete($id, Auth::user());

        return $form($id)->with('v2_flash', t('Return marked complete.'));
    })->whereNumber('id')->name('complete');
});
