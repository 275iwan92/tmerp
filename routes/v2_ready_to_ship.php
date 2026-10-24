<?php

// Ready to Ship (antrean pilih CO -> satu DO Draft lewat Pull Lines). Dimuat dari routes/web.php (di dalam grup auth); prefix v2/do, nama v2.do..
// Aturan: App\Support\V2\Data\ReadyToShip; hak sama dengan layar Delivery Order (modul SALES).

use App\Support\V2\Data\ReadyToShip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v2/do')->name('v2.do.')->middleware('module:SALES')->group(function () {
    Route::post('/from-queue', function (Request $r) {
        [$do, $message] = ReadyToShip::createDo((array) $r->input('ids'));

        if (! $do) {
            return redirect(route('v2.screen', 'ready-to-ship'))->withErrors(['queue' => $message]);
        }

        $back = redirect(route('v2.screen', 'delivery-order').'?id='.$do->id_delivery)
            ->with('v2_flash', tf('DO draft created from :n CO. Check the lines, then Confirm.', ['n' => count(array_unique((array) $r->input('ids')))]));

        return $message ? $back->withErrors(['save' => $message]) : $back;
    })->name('from-queue');
});
