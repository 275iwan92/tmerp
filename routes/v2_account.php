<?php

use App\Support\V2\Data\Account;
use App\Support\V2\Data\SelfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Menu akun Layer Menu v2: Edit profil, Ubah password, Notifikasi (App\Support\V2\Data\Account). Dipanggil lewat fetch dari
// objek DevLib Shell (prop account); balasan JSON {message} atau 422 {message}.
// File route sendiri (dimuat routes/web.php sesudah v2.php) supaya tidak bercampur dengan pekerjaan modul lain di v2.php.
Route::prefix('v2/account')->name('v2.account.')->group(function () {
    $reply = fn (?string $error, string $ok) => $error
        ? response()->json(['message' => $error], 422)
        : response()->json(['message' => $ok]);

    Route::post('/profile', fn (Request $r) => $reply(Account::saveProfile(Auth::user(), $r->only('name', 'email', 'phone')), t('Profile saved.')))->name('profile');

    Route::post('/password', fn (Request $r) => $reply(Account::changePassword(Auth::user(), $r->only('old_password', 'new_password', 'new_password_confirmation')),
        t('Password changed.')))->middleware('throttle:10,1')->name('password');

    Route::post('/notifications/read', fn (Request $r) => response()->json(['unread' => Account::markRead(Auth::user(), $r->filled('id') ? (int) $r->input('id') : null)]))
        ->name('notifications.read');
});

// Edit profile (layar penuh, objek DevLib): data diri, keluarga, dokumen, cuti / sakit / izin, izin keluar, tukar shift,
// jadwal, kartu pengenal, akun + pertanyaan pemulihan (App\Support\V2\Data\SelfService). Dibuka dari Profile > Edit profile
// sebagai tab kerja; bukan item menu modul, jadi tidak lewat Screens / sys_app_menu (user 2026-10-03).
// Help & support (laci lebar): Help Center, pedoman, alur dokumen, Support Ticket.
Route::prefix('v2')->name('v2.')->middleware(['auth', 'locale', 'force.password'])->group(function () {
    Route::get('/help', function () {
        $screen = ['slug' => 'help', 'label' => 'Help & support', 'icon' => 'fa-solid fa-circle-question', 'module' => 'Account', 'group' => 'Help',
            'url' => route('v2.help'), 'filters' => ['tab'], 'side' => true, 'view' => 'v2.screens.help', 'ref' => 'v2/home.blade.php (profilePages, sebelum 2026-10-03)', 'mockup' => null];

        return view('v2.screen', ['screen' => $screen, 'built' => true, 'code' => file_get_contents(resource_path('views/v2/screens/help.blade.php'))]);
    })->name('help');
});

Route::prefix('v2/profile')->name('v2.profile')->middleware(['auth', 'locale', 'force.password'])->group(function () {
    Route::get('/', function () {
        $screen = ['slug' => 'profile', 'label' => 'Edit Profile', 'icon' => 'fa-solid fa-user-pen', 'module' => 'Account', 'group' => 'Profile',
            'url' => route('v2.profile'), 'filters' => ['tab'], 'side' => true, 'view' => 'v2.screens.profile', 'ref' => 'self-service v1 (dihapus 2026-10-18)', 'mockup' => null];

        return view('v2.screen', ['screen' => $screen, 'built' => true, 'code' => file_get_contents(resource_path('views/v2/screens/profile.blade.php'))]);
    })->name('');

    $emp = fn () => SelfService::employee(Auth::user()) ?? abort(403, t('This account is not linked to an employee record.'));
    // Setiap aksi: null = sukses (pesan hijau), string = pesan error; keduanya kembali ke tab yang sama.
    $run = function (string $tab, callable $fn, string $ok) {
        $err = $fn();
        $to = route('v2.profile').'?tab='.$tab;

        return $err ? redirect($to)->withInput()->withErrors(['profile' => $err]) : redirect($to)->with('v2_flash', $ok);
    };

    Route::post('/correction', fn (Request $r) => $run('profile', fn () => SelfService::saveCorrection($emp(), $r), t('Correction request sent to HR — status Pending.')))->name('.correction');
    Route::post('/resign', fn (Request $r) => $run('profile', fn () => SelfService::saveResign($emp(), $r), t('Resignation request sent — HR will contact you.')))->name('.resign');
    Route::post('/family', fn (Request $r) => $run('family', fn () => SelfService::saveFamily($emp(), $r), t('Family request sent to HR — status Pending.')))->name('.family');
    Route::post('/document', fn (Request $r) => $run('documents', fn () => SelfService::saveDocument($emp(), $r), t('Document uploaded.')))->name('.document');
    Route::post('/education', fn (Request $r) => $run('documents', fn () => SelfService::saveEducation($emp(), $r), t('Training history added.')))->name('.education');
    Route::post('/leave', fn (Request $r) => $run('leave', fn () => SelfService::submitLeave($emp(), $r), t('Request sent to HRD — status Waiting for approval.')))->name('.leave');
    Route::post('/outing', fn (Request $r) => $run('outing', fn () => SelfService::submitOutPermit($emp(), $r), t('Out permit sent — waiting for supervisor and HRD.')))->name('.outing');
    Route::post('/swap', fn (Request $r) => $run('swap', fn () => SelfService::submitSwap($emp(), $r), t('Shift swap request sent — waiting for the partner, then PPIC.')))->name('.swap');
    Route::post('/badge', fn (Request $r) => $run('badge', fn () => SelfService::saveBadgePhoto($emp(), $r), t('Photo sent — HRD reviews it before reprinting.')))->name('.badge');
    Route::post('/account', fn (Request $r) => $run('security', fn () => Account::saveProfile(Auth::user(), $r->only('name', 'email', 'phone')), t('Profile saved.')))->name('.account');
    Route::post('/password', fn (Request $r) => $run('security', fn () => Account::changePassword(Auth::user(), $r->only('old_password', 'new_password', 'new_password_confirmation')), t('Password changed.')))
        ->middleware('throttle:10,1')->name('.password');
    Route::post('/security', fn (Request $r) => $run('security', fn () => SelfService::saveSecurity(Auth::user(), $r), t('Account recovery data saved.')))->name('.security');
});
