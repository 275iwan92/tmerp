<?php

use App\Models\Master\CustCustomer;
use App\Models\Master\MatRaw;
use App\Models\Master\SupSupplier;
use App\Services\Master\CustomerMasterService;
use App\Services\Master\MaterialMasterService;
use App\Services\Master\SupplierMasterService;
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

// Master Supplier (layar md-supplier-form): save / activate / deactivate / block (Block atau Unblock) / delete (hapus, atau nonaktif bila masih dipakai), kontak dan cabang tambahan (dialog per baris).
// Aturan = App\Services\Master\SupplierMasterService + SupplierGuardService (dipindah dari v1 pages::master.supplier).
Route::prefix('v2/md/supplier')->name('v2.md.supplier.')->middleware('module:MDATA')->group(function () {
    $form = fn (SupSupplier $s, string $tab = 'identity') => redirect(route('v2.screen', 'md-supplier-form').'?id='.$s->id_supplier.'&tab='.$tab);
    $supplier = fn (int $id) => SupSupplier::findOrFail($id);

    Route::post('/save', function (Request $r, SupplierMasterService $svc) use ($form) {
        $s = $r->filled('id') ? SupSupplier::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();

        if ($s && in_array($action, ['activate', 'deactivate'], true)) {
            $svc->setActive($s, $action === 'activate', $user);

            return $form($s)->with('v2_flash', tf($action === 'activate' ? ':name activated.' : ':name deactivated.', ['name' => $s->supplier_name]));
        }
        if ($s && $action === 'block') {
            return $form($s)->with('v2_flash', $svc->toggleBlock($s, $user) ? t('Supplier blocked.') : t('Supplier unblocked.'));
        }
        if ($s && $action === 'delete') {
            $reasons = $svc->delete($s, $user);
            if ($reasons === []) {
                return redirect(route('v2.screen', 'md-supplier'))->with('v2_flash', t('Supplier permanently deleted.'));
            }

            return $form($s)->with('v2_flash', tf('Cannot be deleted: still used by :reasons. The supplier has been deactivated instead.', ['reasons' => implode(' and ', $reasons)]));
        }
        $s = $svc->save($s, $r->except(['id', 'action']), $user);

        return $form($s)->with('v2_flash', tf(':name saved.', ['name' => $s->supplier_name]));
    })->name('save');

    Route::post('/{id}/branch/{branch?}', function (Request $r, int $id, SupplierMasterService $svc, ?int $branch = null) use ($form, $supplier) {
        $svc->saveBranch($s = $supplier($id), $branch, $r->all(), Auth::user());

        return $form($s, 'address')->with('v2_flash', t('Branch saved.'));
    })->whereNumber(['id', 'branch'])->name('branch');
    Route::post('/{id}/branch/{branch}/default', function (int $id, int $branch, SupplierMasterService $svc) use ($form, $supplier) {
        $svc->setDefaultBranch($s = $supplier($id), $branch);

        return $form($s, 'address')->with('v2_flash', t('Default branch changed.'));
    })->whereNumber(['id', 'branch'])->name('branch.default');
    Route::post('/{id}/branch/{branch}/delete', function (int $id, int $branch, SupplierMasterService $svc) use ($form, $supplier) {
        $svc->deleteBranch($s = $supplier($id), $branch);

        return $form($s, 'address')->with('v2_flash', t('Branch removed.'));
    })->whereNumber(['id', 'branch'])->name('branch.delete');

    Route::post('/{id}/contact/{contact?}', function (Request $r, int $id, SupplierMasterService $svc, ?int $contact = null) use ($form, $supplier) {
        $svc->saveContact($s = $supplier($id), $contact, $r->all(), Auth::user());

        return $form($s, 'contact')->with('v2_flash', t('Contact saved.'));
    })->whereNumber(['id', 'contact'])->name('contact');
    Route::post('/{id}/contact/{contact}/default', function (int $id, int $contact, SupplierMasterService $svc) use ($form, $supplier) {
        $svc->setDefaultContact($s = $supplier($id), $contact);

        return $form($s, 'contact')->with('v2_flash', t('Default contact changed.'));
    })->whereNumber(['id', 'contact'])->name('contact.default');
    Route::post('/{id}/contact/{contact}/delete', function (int $id, int $contact, SupplierMasterService $svc) use ($form, $supplier) {
        $svc->deleteContact($s = $supplier($id), $contact);

        return $form($s, 'contact')->with('v2_flash', t('Contact removed.'));
    })->whereNumber(['id', 'contact'])->name('contact.delete');
});

// Master Raw Material (layar md-raw-form): save / confirm / activate / deactivate / delete (hapus, atau nonaktif bila masih dipakai).
// Aturan = App\Services\Master\MaterialMasterService + MaterialGuardService (dipindah dari v1 pages::master.raw-material.entry).
Route::prefix('v2/md/raw')->name('v2.md.raw.')->middleware('module:MDATA')->group(function () {
    $form = fn (MatRaw $m, string $tab = 'category') => redirect(route('v2.screen', 'md-raw-form').'?id='.$m->material_id.'&tab='.$tab);

    Route::post('/save', function (Request $r, MaterialMasterService $svc) use ($form) {
        $m = $r->filled('id') ? MatRaw::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        $user = Auth::user();

        if ($m && in_array($action, ['activate', 'deactivate'], true)) {
            $svc->setActive($m, $action === 'activate', $user);

            return $form($m)->with('v2_flash', tf($action === 'activate' ? ':name activated.' : ':name deactivated.', ['name' => $m->material_name]));
        }
        if ($m && $action === 'delete') {
            $reasons = $svc->delete($m, $user);
            if ($reasons === []) {
                return redirect(route('v2.screen', 'md-raw'))->with('v2_flash', t('Item permanently deleted.'));
            }

            return $form($m)->with('v2_flash', tf('Cannot be deleted: still used by :reasons. The item has been deactivated instead.', ['reasons' => implode(' and ', $reasons)]));
        }
        if ($m && $action === 'confirm') {
            $m = $svc->confirm($m, $r->except(['id', 'action']), $user);

            return $form($m)->with('v2_flash', tf(':name confirmed. Category and item code are now locked.', ['name' => $m->material_name]));
        }
        $m = $svc->save($m, $r->except(['id', 'action']), $user);

        return $form($m)->with('v2_flash', tf(':name saved.', ['name' => $m->material_name]));
    })->name('save');
});
