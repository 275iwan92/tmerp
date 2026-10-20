<?php

/*
 * REMARK
 * Tujuan       : Route layar Setup v2 yang menggantikan halaman v1: akun login (security-account-form) dan grup akses (security-group-form). Semua di balik middleware admin (hanya administrator, SecUser::canSetup).
 * Sumber hulu  : App\Services\Security\SecurityAccountService dan SecurityGroupService (reset password dan aktif / nonaktif akun memakai EmployeeAccountService).
 * Input        : POST form (id opsional, action save, reset, reveal, toggle).
 * Output       : Redirect ke form (id) dengan flash v2_flash / errors; reset dan reveal menitipkan kartu login (v2_login_card, sekali tampil).
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris web.php yang dikutip kartu TG tidak bergeser); layar security-account-form dan security-group-form.
 * Status       : Aktif. Dibuat 2026-10-20 (T8 Setup, Jo): menggantikan halaman v1 pages::security.index (tab users dan groups; tab perms dan migration sudah v2).
 */

use App\Models\Sys\SecGroup;
use App\Models\Sys\SecUser;
use App\Services\Security\SecurityAccountService;
use App\Services\Security\SecurityGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Akun: save (baru / ubah), reset password, lihat password bawaan, aktif / nonaktif. Aturan = SecurityAccountService (dipindah dari v1 pages::security.index tab users).
Route::prefix('v2/setup/account')->name('v2.setup.account.')->middleware(['admin'])->group(function () {
    Route::post('/save', function (Request $r, SecurityAccountService $svc) {
        $actor = Auth::user();
        $u = $r->filled('id') ? SecUser::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        abort_unless(in_array($action, ['save', 'reset', 'reveal', 'toggle'], true), 422);
        abort_if(! $u && $action !== 'save', 422);
        $card = null;

        if ($action === 'save') {
            $u = $svc->save($u, $r->only(['login', 'name', 'email', 'password', 'group_id']), $actor);
            $msg = t($r->filled('id') ? 'Account saved.' : 'Account created.');
        } elseif ($action === 'reset') {
            $card = $svc->resetPassword($u) + ['title' => t('New password')];
            $msg = t('Password reset. Give the new password to the user by hand.');
        } elseif ($action === 'reveal') {
            $card = $svc->reveal($u) + ['title' => t('Default password')];
            $msg = t('Default password shown once.');
        } else {
            $svc->setActive($u, ! $u->activate, $actor);
            $msg = t($u->activate ? 'Account activated.' : 'Account deactivated.');
        }

        return redirect(route('v2.screen', 'security-account-form').'?id='.$u->id_user)->with('v2_flash', $msg)->with('v2_login_card', $card);
    })->name('save');
});

// Grup: save (baru / ubah) dan aktif / nonaktif. Aturan = SecurityGroupService (dipindah dari v1 pages::security.index tab groups).
Route::prefix('v2/setup/group')->name('v2.setup.group.')->middleware(['admin'])->group(function () {
    Route::post('/save', function (Request $r, SecurityGroupService $svc) {
        $actor = Auth::user();
        $g = $r->filled('id') ? SecGroup::findOrFail((int) $r->input('id')) : null;
        $action = (string) $r->input('action', 'save');
        abort_unless(in_array($action, ['save', 'toggle'], true), 422);
        abort_if(! $g && $action !== 'save', 422);

        if ($action === 'save') {
            $g = $svc->save($g, $r->only(['group_code', 'group_name', 'mgmt_level']), $actor);
            $msg = t($r->filled('id') ? 'Group saved.' : 'Group created.');
        } else {
            $svc->setActive($g, ! $g->activate, $actor);
            $msg = t($g->activate ? 'Group activated.' : 'Group deactivated.');
        }

        return redirect(route('v2.screen', 'security-group-form').'?id='.$g->id_group)->with('v2_flash', $msg);
    })->name('save');
});
