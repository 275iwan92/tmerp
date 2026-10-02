<?php

use App\Support\V2\Data\Account;
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
