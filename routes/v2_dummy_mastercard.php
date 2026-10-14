<?php

/*
 * REMARK
 * Tujuan       : Layar Master Card Dummy (menu Developer > Database): jumlah set dummy per level dan tombol Run engine PDF Master Card -> set berlevel.
 * Sumber hulu  : App\Services\MasterCard\MasterCardDummyEngine (counts, plan, run); folder docs/attachments/mastercard.
 * Input        : GET tanpa parameter; POST jumlah set baru per level (inquiry, prodev, procost, co), qty order, hari due date.
 * Output       : Halaman v2.dummy-mastercard (jumlah set per level, PDF siap, hasil terakhir). Data dummy ditulis engine hanya ke ddt*.
 * Dipakai oleh : Menu Developer > Database > Master Card Dummy (PDF) (App\Support\V2\AppMenu).
 * Status       : Aktif; developer saja, hanya situs dummy ddt*. File sendiri (dimuat routes/web.php) supaya tidak bercampur dengan v2.php.
 */

use App\Services\MasterCard\MasterCardDummyEngine;
use App\Support\V2\AppMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::prefix('v2/dummy/mastercard')->group(function () {
    $dir = base_path('docs/attachments/mastercard');
    $guard = fn () => abort_unless(AppMenu::user(Auth::user())['developer'], 403);

    Route::get('/', function () use ($guard, $dir) {
        $guard();
        $engine = app(MasterCardDummyEngine::class);
        $plan = collect($engine->plan($dir));

        return view('v2.dummy-mastercard', ['counts' => $engine->counts(), 'ready' => $plan->where('status', 'ready')->count(), 'total' => $plan->count(),
            'report' => session('v2_mc_report', [])]);
    })->name('v2.dummy.mastercard');

    Route::post('/', function (Request $r) use ($guard, $dir) {
        $guard();
        $rules = array_fill_keys(MasterCardDummyEngine::LEVELS, 'required|integer|min:0|max:20') + ['qty' => 'required|integer|min:1|max:1000000', 'due' => 'required|integer|min:1|max:365'];
        $d = $r->validate($rules, [], ['inquiry' => t('Inquiry'), 'prodev' => t('ProDev'), 'procost' => t('ProCost'), 'co' => t('CO'), 'qty' => t('Order qty'), 'due' => t('Due (days)')]);
        $quota = Arr::only($d, MasterCardDummyEngine::LEVELS);
        if (array_sum($quota) < 1) {
            return back()->withInput()->withErrors(['inquiry' => t('Enter at least one set.')]);
        }
        set_time_limit(0);
        try {
            $report = app(MasterCardDummyEngine::class)->run($dir, $quota, (int) $d['qty'], (int) $d['due']);
        } catch (Throwable $e) {
            return back()->withInput()->with('v2_error', raw($e->getMessage()));
        }
        $built = count(array_filter($report, fn ($row) => $row['level']));

        return back()->with('v2_flash', raw(tf(':n product(s) built.', ['n' => $built])))->with('v2_mc_report', $report);
    })->name('v2.dummy.mastercard.run');
});
