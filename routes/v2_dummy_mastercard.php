<?php

/*
 * REMARK
 * Tujuan       : Layar info set dummy Master Card per level (menu Developer > Database). Tahap ini hanya info; engine dijalankan lewat perintah dummy:mastercard.
 * Sumber hulu  : App\Services\MasterCard\MasterCardDummyEngine (counts, plan); folder docs/attachments/mastercard.
 * Input        : GET tanpa parameter.
 * Output       : Halaman v2.dummy-mastercard dengan jumlah set per level dan jumlah PDF siap dibuat. Tidak menulis apa pun.
 * Dipakai oleh : Menu Developer > Database > Master Card Dummy (PDF) (App\Support\V2\AppMenu).
 * Status       : Aktif; developer saja, hanya situs dummy ddt*. File sendiri (dimuat routes/web.php) supaya tidak bercampur dengan v2.php.
 */

use App\Services\MasterCard\MasterCardDummyEngine;
use App\Support\V2\AppMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('v2/dummy/mastercard', function () {
    abort_unless(AppMenu::user(Auth::user())['developer'], 403);
    $engine = app(MasterCardDummyEngine::class);
    $plan = collect($engine->plan(base_path('docs/attachments/mastercard')));

    return view('v2.dummy-mastercard', ['counts' => $engine->counts(), 'ready' => $plan->where('status', 'ready')->count(), 'total' => $plan->count()]);
})->name('v2.dummy.mastercard');
