<?php

use App\Models\Master\CustCustomer;
use App\Services\Master\CustomerMasterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Master Data v2 (T4, user 2026-10-18): form master yang menggantikan halaman v1. File route sendiri (dimuat routes/web.php di baris yang sama
// dengan v2_finance.php, supaya nomor baris web.php yang dikutip kartu TG tidak bergeser).

// Master Customer (layar md-customer-form): save / activate / deactivate / block, unblock (dialog), kontak dan cabang tambahan (dialog per baris).
// Aturan = App\Services\Master\CustomerMasterService (dipindah dari v1 pages::master.customer).
Route::prefix('v2/md/customer')->name('v2.md.customer.')->middleware('module:MDATA')->group(function () {
    $form = fn (CustCustomer $c, string $tab = 'overview') => redirect(route('v2.screen', 'md-customer-form').'?id='.$c->id_customer.'&tab='.$tab);
    $customer = fn (int $id) => CustCustomer::findOrFail($id);

    Route::post('/save', function (Request $r, CustomerMasterService $svc) use ($form) {
        $c = $r->filled('id') ? CustCustomer::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();

        if ($c && in_array($action, ['activate', 'deactivate'], true)) {
            $svc->setActive($c, $action === 'activate', $user);

            return $form($c)->with('v2_flash', tf($action === 'activate' ? ':name activated.' : ':name deactivated.', ['name' => $c->customer_name]));
        }
        if ($c && $action === 'block') {
            $svc->block($c, $user);

            return $form($c)->with('v2_flash', t('Customer blocked.'));
        }
        $c = $svc->save($c, $r->except(['id', 'action']), $user);

        return $form($c)->with('v2_flash', tf(':name saved.', ['name' => $c->customer_name]));
    })->name('save');

    Route::post('/{id}/unblock', function (Request $r, int $id, CustomerMasterService $svc) use ($form, $customer) {
        $svc->unblock($c = $customer($id), (string) $r->input('unblock_reason'), Auth::user());

        return $form($c)->with('v2_flash', t('Customer unblocked.'));
    })->whereNumber('id')->name('unblock');

    // Cabang tambahan: baru (/branch), ubah (/branch/{b}), jadikan default (/branch/{b}/default), hapus (/branch/{b}/delete).
    Route::post('/{id}/branch/{branch?}', function (Request $r, int $id, CustomerMasterService $svc, ?int $branch = null) use ($form, $customer) {
        $svc->saveBranch($c = $customer($id), $branch, $r->all(), Auth::user());

        return $form($c, 'sites')->with('v2_flash', t('Branch saved.'));
    })->whereNumber(['id', 'branch'])->name('branch');
    Route::post('/{id}/branch/{branch}/default', function (int $id, int $branch, CustomerMasterService $svc) use ($form, $customer) {
        $svc->setDefaultBranch($c = $customer($id), $branch);

        return $form($c, 'sites')->with('v2_flash', t('Default branch changed.'));
    })->whereNumber(['id', 'branch'])->name('branch.default');
    Route::post('/{id}/branch/{branch}/delete', function (int $id, int $branch, CustomerMasterService $svc) use ($form, $customer) {
        $svc->deleteBranch($c = $customer($id), $branch);

        return $form($c, 'sites')->with('v2_flash', t('Branch removed.'));
    })->whereNumber(['id', 'branch'])->name('branch.delete');

    // Kontak tambahan: baru (/contact), ubah (/contact/{k}), jadikan default (/contact/{k}/default), hapus (/contact/{k}/delete).
    Route::post('/{id}/contact/{contact?}', function (Request $r, int $id, CustomerMasterService $svc, ?int $contact = null) use ($form, $customer) {
        $svc->saveContact($c = $customer($id), $contact, $r->all(), Auth::user());

        return $form($c, 'sites')->with('v2_flash', t('Contact saved.'));
    })->whereNumber(['id', 'contact'])->name('contact');
    Route::post('/{id}/contact/{contact}/default', function (int $id, int $contact, CustomerMasterService $svc) use ($form, $customer) {
        $svc->setDefaultContact($c = $customer($id), $contact);

        return $form($c, 'sites')->with('v2_flash', t('Default contact changed.'));
    })->whereNumber(['id', 'contact'])->name('contact.default');
    Route::post('/{id}/contact/{contact}/delete', function (int $id, int $contact, CustomerMasterService $svc) use ($form, $customer) {
        $svc->deleteContact($c = $customer($id), $contact);

        return $form($c, 'sites')->with('v2_flash', t('Contact removed.'));
    })->whereNumber(['id', 'contact'])->name('contact.delete');
});
