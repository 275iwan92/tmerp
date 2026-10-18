<?php

use App\Models\Acc\FinAccount;
use App\Services\Acc\ChartOfAccountService;
use App\Support\V2\Data\CustomerInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Customer Invoice v2 (App\Support\V2\Data\CustomerInvoice -> App\Services\Acc\CustomerInvoiceService).
// File route sendiri (dimuat routes/web.php sesudah v2.php) supaya tidak bercampur dengan pekerjaan modul lain di v2.php.
//   create  : Draft dari Customer Order (Invoice = baris Surat Jalan yang dicentang; Down Payment = dari nilai order)
//   save    : action = save | confirm | cancel | delete
//   receipt : uang muka diterima (dialog Record Receipt di form Down Payment)
Route::prefix('v2/customer-invoice')->name('v2.customer-invoice.')->middleware('module:FINACC')->group(function () {
    $home = fn () => route('v2.screen', 'customer-invoice');
    $open = fn (int $id) => redirect($home().'?id='.$id);

    Route::post('/create', function (Request $r) use ($home, $open) {
        $d = $r->validate(['type' => ['required', 'in:INVOICE,DOWN_PAYMENT'], 'co_id' => ['required', 'integer']], [], ['co_id' => t('Customer Order')]);
        $picked = collect((array) $r->input('lines', []))->filter(fn ($row) => filter_var(((array) $row)['pick'] ?? false, FILTER_VALIDATE_BOOLEAN))->keys()->all();
        if ($d['type'] === 'INVOICE' && ! $picked) {
            return redirect($home().'?new=1&type=INVOICE&co_id='.$d['co_id'])->withErrors(['lines' => t('Choose at least one delivery note line to invoice.')]);
        }
        try {
            $inv = CustomerInvoice::create($d['type'], (int) $d['co_id'], $picked);
        } catch (DomainException $e) {
            return redirect($home().'?new=1&type='.$d['type'])->withErrors(['create' => t($e->getMessage())]);
        }

        return $open($inv->invoice_id)->with('v2_flash', t('Draft created. Check it, then Confirm.'));
    })->name('create');

    Route::post('/{id}/save', function (Request $r, int $id) use ($home, $open) {
        $inv = CustomerInvoice::find($id) ?? abort(404);
        $action = (string) $r->input('action', 'save');
        if ($action === 'delete') {
            $err = CustomerInvoice::act($inv, 'delete');

            return $err ? $open($id)->withErrors(['delete' => t($err)]) : redirect($home())->with('v2_flash', t('Draft deleted.'));
        }
        if ($action === 'cancel') {
            $err = CustomerInvoice::act($inv, 'cancel', (string) $r->input('_reason', ''));

            return $err ? $open($id)->withErrors(['cancel' => t($err)]) : $open($id)->with('v2_flash', t('Document cancelled.'));
        }
        $r->validate(['invoice_date' => ['nullable', 'date'], 'company_bank_id' => ['nullable', 'integer']]);
        try {
            CustomerInvoice::save($inv, $r->all());
        } catch (DomainException $e) {
            return $open($id)->withErrors(['save' => t($e->getMessage())])->withInput();
        }
        if ($action === 'confirm') {
            $err = CustomerInvoice::act(CustomerInvoice::find($id), 'confirm');

            return $err ? $open($id)->withErrors(['confirm' => $err]) : $open($id)->with('v2_flash', t('Confirmed. The number is issued and the document is ready to print.'));
        }

        return $open($id)->with('v2_flash', t('Saved.'));
    })->whereNumber('id')->name('save');

    Route::post('/{id}/receipt', function (Request $r, int $id) use ($open) {
        $inv = CustomerInvoice::find($id) ?? abort(404);
        $r->validate(['received_date' => ['required', 'date'], 'company_bank_id' => ['required', 'integer']], [], ['received_date' => t('Received Date'), 'company_bank_id' => t('Received In')]);
        $err = CustomerInvoice::receive($inv, $r->all());

        return $err ? $open($id)->withErrors(['receipt' => t($err)]) : $open($id)->with('v2_flash', t('Down payment received. The receipt is ready to print.'));
    })->whereNumber('id')->name('receipt');

    // Pelunasan diterima (dialog Record Payment di form invoice): satu penerimaan untuk satu atau beberapa invoice customer itu.
    Route::post('/{id}/payment', function (Request $r, int $id) use ($open) {
        $inv = CustomerInvoice::find($id) ?? abort(404);
        $r->validate(['received_date' => ['required', 'date'], 'company_bank_id' => ['required', 'integer']], [], ['received_date' => t('Received Date'), 'company_bank_id' => t('Received In')]);
        $err = CustomerInvoice::pay($inv, $r->all());

        return $err ? $open($id)->withErrors(['payment' => $err]) : $open($id)->with('v2_flash', t('Payment received and allocated to the invoice.'));
    })->whereNumber('id')->name('payment');
});

// Master of Accounts v2 (layar acc-coa-form): save / activate / deactivate akun GL. Aturan = App\Services\Acc\ChartOfAccountService (dipindah dari v1 pages::finance.coa).
Route::prefix('v2/acc/coa')->name('v2.acc.coa.')->middleware('module:FINACC')->group(function () {
    Route::post('/save', function (Request $r, ChartOfAccountService $svc) {
        $a = $r->filled('id') ? FinAccount::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        if ($a && in_array($action, ['activate', 'deactivate'], true)) {
            $svc->setActive($a, $action === 'activate', Auth::user());
            $msg = $action === 'activate' ? ':name activated.' : ':name deactivated.';
        } else {
            $a = $svc->save($a, $r->except(['id', 'action']), Auth::user());
            $msg = ':name saved.';
        }

        return redirect(route('v2.screen', 'acc-coa-form').'?id='.$a->account_id)->with('v2_flash', tf($msg, ['name' => $a->account_name]));
    })->name('save');
});
