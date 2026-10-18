<?php

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinVoucher;
use App\Services\Acc\CashBankVoucherService;
use App\Services\Acc\ChartOfAccountService;
use App\Services\Acc\ManualJournalService;
use App\Support\V2\Data\CustomerInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

// Journal Entries v2 (layar acc-journal-form, form dokumen): save / confirm (= ajukan) / delete / approve / return / post, dan reverse (dialog). Aturan = App\Services\Acc\ManualJournalService (dipindah dari v1 pages::finance.journals).
Route::prefix('v2/acc/journal')->name('v2.acc.journal.')->middleware('module:FINACC')->group(function () {
    $form = fn (int $id) => redirect(route('v2.screen', 'acc-journal-form').'?id='.$id);
    // Pelanggaran aturan bisnis (DomainException / abort 422) tampil sebagai pesan di form, bukan halaman error.
    $guard = function (callable $run) {
        try {
            return $run();
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['journal' => $e->getMessage()]);
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 422) {
                throw $e;
            }

            return back()->withInput()->withErrors(['journal' => $e->getMessage()]);
        }
    };

    Route::post('/save', function (Request $r, ManualJournalService $svc) use ($form, $guard) {
        $v = $r->filled('id') ? FinVoucher::with('lines')->findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();

        return $guard(function () use ($r, $svc, $form, $v, $action, $user) {
            if ($v && $action === 'delete') {
                $svc->delete($v);

                return redirect(route('v2.screen', 'acc-journal'))->with('v2_flash', t('Draft deleted.'));
            }
            if (in_array($action, ['save', 'confirm'], true)) {
                $v = $svc->save($v, $r->only(['entry_date', 'description', 'journal_kind', 'reference_no', 'auto_reverse_date']), (array) $r->input('lines', []), $user);
                if ($action === 'confirm') {
                    $svc->submit($v->fresh('lines'), $user);
                    $msg = $v->fresh()->status === 'APPROVED' ? t('Journal submitted. No approval rule applies, so it is Approved: post it to the GL.') : t('Journal submitted for approval.');
                } else {
                    $msg = t('Draft journal saved.');
                }

                return $form($v->voucher_id)->with('v2_flash', $msg);
            }
            abort_unless($v, 404);
            match ($action) {
                'approve' => $svc->approve($v, $user),
                'reject', 'return' => $svc->returnToDraft($v, $user),
                'post' => $svc->post($v, $user),
                default => abort(422, 'Unknown action.'),
            };

            return $form($v->voucher_id)->with('v2_flash', ['approve' => t('Journal approved.'), 'reject' => t('Journal returned to Draft.'), 'return' => t('Journal returned to Draft.'), 'post' => t('Journal posted to the General Ledger.')][$action]);
        });
    })->name('save');

    Route::post('/{id}/reverse', function (Request $r, int $id, ManualJournalService $svc) use ($form, $guard) {
        $v = FinVoucher::with('lines')->findOrFail($id);

        return $guard(function () use ($r, $svc, $form, $v) {
            $rev = $svc->reverse($v, (string) $r->input('reverse_date'), (string) $r->input('reverse_reason'), Auth::user());

            return $form($v->voucher_id)->with('v2_flash', tf('Reversing journal :no posted.', ['no' => $rev->voucher_number]));
        });
    })->whereNumber('id')->name('reverse');
});

// Cash & Bank v2 (layar acc-cash-bank-form, form dokumen): save / confirm (= simpan lalu Post ke GL) / cancel (draft). Aturan = App\Services\Acc\CashBankVoucherService (dipindah dari v1 pages::finance.cash-bank).
Route::prefix('v2/acc/cash-bank')->name('v2.acc.cashbank.')->middleware('module:FINACC')->group(function () {
    Route::post('/save', function (Request $r, CashBankVoucherService $svc) {
        $v = $r->filled('id') ? FinVoucher::with('lines')->findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();
        $open = fn (FinVoucher $x) => redirect(route('v2.screen', 'acc-cash-bank-form').'?id='.$x->voucher_id);

        try {
            if ($v && $action === 'cancel') {
                $svc->cancel($v, $user);

                return redirect(route('v2.screen', 'acc-cash-bank'))->with('v2_flash', t('Draft cancelled.'));
            }
            if ($v && $action === 'duplicate') {
                return $open($svc->duplicate($v, $user))->with('v2_flash', t('Copied into a new Draft.'));
            }
            $v = $svc->save($v, $r->only(['voucher_type', 'entry_date', 'main_account_id', 'partner_name', 'reference_no', 'ppn_type', 'efaktur_no', 'pph_type', 'bupot_no']), (array) $r->input('lines', []), $user);
            if ($action === 'confirm') {
                $svc->post($v, $user);

                return $open($v)->with('v2_flash', tf('Voucher :no posted to the General Ledger.', ['no' => $v->voucher_number]));
            }

            return $open($v)->with('v2_flash', tf('Draft :no saved.', ['no' => $v->voucher_number]));
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 422) {
                throw $e;
            }

            // Bila simpan berhasil tetapi Post ditolak (mis. tidak seimbang), draft tetap tersimpan dan pesannya tampil di form.
            return ($v?->voucher_id ? $open($v) : back()->withInput())->withErrors(['voucher' => $e->getMessage()]);
        }
    })->name('save');
});
