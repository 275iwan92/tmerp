<?php

/*
 * REMARK
 * Tujuan       : Layar info Master Card Dummy (menu Developer > Database) dan tombol Run engine PDF Master Card -> data dummy berlevel.
 * Sumber hulu  : App\Services\MasterCard\MasterCardDummyEngine (counts, plan, run); folder docs/attachments/mastercard.
 * Input        : GET tanpa parameter; POST tanpa isian: tiap PDF siap dibagi bergiliran ke level Inquiry, ProDev, ProCost, CO (qty 5000, due 21 hari).
 * Output       : Halaman v2.dummy-mastercard (jumlah PDF dan set per level) + pesan hasil. Data dummy ditulis engine hanya ke ddt*.
 * Dipakai oleh : Menu Developer > Database > Master Card Dummy (PDF) (App\Support\V2\AppMenu).
 * Status       : Aktif; developer saja, hanya situs dummy ddt*. File sendiri (dimuat routes/web.php) supaya tidak bercampur dengan v2.php.
 */

use App\Services\MasterCard\MasterCardDummyEngine;
use App\Support\V2\AppMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::prefix('v2/dummy/mastercard')->group(function () {
    $dir = base_path('docs/attachments/mastercard');
    $guard = fn () => abort_unless(AppMenu::user(Auth::user())['developer'], 403);

    Route::get('/', function () use ($guard, $dir) {
        $guard();
        $engine = app(MasterCardDummyEngine::class);
        $plan = collect($engine->plan($dir));

        return view('v2.dummy-mastercard', ['counts' => $engine->counts(), 'ready' => $plan->where('status', 'ready')->count(), 'total' => $plan->count()]);
    })->name('v2.dummy.mastercard');

    Route::post('/', function () use ($guard, $dir) {
        $guard();
        $engine = app(MasterCardDummyEngine::class);
        $ready = collect($engine->plan($dir))->where('status', 'ready')->count();
        if ($ready < 1) {
            return back()->with('v2_flash', raw(t('No PDF is ready to build.')));
        }
        // Ready PDFs are spread over the levels in turn, so every level gets sets to test with.
        $levels = MasterCardDummyEngine::LEVELS;
        $quota = array_fill_keys($levels, 0);
        for ($i = 0; $i < $ready; $i++) {
            $quota[$levels[$i % count($levels)]]++;
        }
        set_time_limit(0);
        try {
            $report = $engine->run($dir, $quota, 5000, 21);
        } catch (Throwable $e) {
            return back()->with('v2_error', raw($e->getMessage()));
        }

        return back()->with('v2_flash', raw(tf(':n product(s) built.', ['n' => count(array_filter($report, fn ($row) => $row['level']))])));
    })->name('v2.dummy.mastercard.run');
});
