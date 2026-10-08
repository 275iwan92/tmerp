<?php

use App\Models\Inventory\RmBooking;
use App\Models\Inventory\RmBookingD;
use App\Support\Localization\Dictionary;
use App\Support\V2\Data\MaterialPlanning;
use App\Support\V2\Data\PreBookings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Material Planning v2 (docs/materialbooking_tier1.md): Booking per CO + MRP. Logika = App\Services\Planning\MaterialPlanningEngine.
// File route sendiri (dimuat routes/web.php sesudah v2.php) supaya tidak bercampur dengan pekerjaan modul lain di v2.php.
Route::prefix('v2/planning')->name('v2.planning.')->middleware('module:PROD')->group(function () {
    $booking = fn (int $coId) => route('v2.screen', 'material-booking').'?id='.$coId;

    // Hitung ulang & simpan usulan semua CO CONFIRMED yang bookingnya belum dikunci.
    Route::post('/run-all', function () {
        MaterialPlanning::changed();
        $n = MaterialPlanning::engine()->runAll();
        MaterialPlanning::changed();

        return back()->with('v2_flash', str_replace(':n', (string) $n, Dictionary::t('Recalculated: :n confirmed orders.')));
    })->name('run-all');

    Route::post('/booking/{co}', function (Request $r, int $co) use ($booking) {
        $engine = MaterialPlanning::engine();
        $b = RmBooking::where('co_id', $co)->where('stat_cancel', 0)->first();
        $msg = match ($r->input('action')) {
            'confirm' => (function () use ($engine, $b) {
                abort_unless($b, 422, t('Recalculate first.'));
                $engine->confirm($b);

                return 'Booking confirmed: stock is locked for this order.';
            })(),
            'unlock' => (function () use ($engine, $b) {
                abort_unless($b?->stat_confirm, 422, t('Booking is not confirmed.'));
                $engine->unlock($b);

                return 'Booking is back to Draft and recalculated.';
            })(),
            default => $engine->persist($co) ? 'Booking recalculated.' : 'Only a Confirmed Customer Order can be booked.',
        };
        MaterialPlanning::changed();

        return redirect($booking($co))->with('v2_flash', Dictionary::t($msg));
    })->name('booking');

    Route::post('/pick/{co}', function (Request $r, int $co) use ($booking) {
        $d = $r->validate(['line_id' => ['required', 'integer'], 'material_id' => ['required', 'integer', 'exists:tm2mst.mat_raw,material_id']], [], ['material_id' => t('Stock Item'), 'line_id' => t('Line')]);
        $line = RmBookingD::where('booking_detail_id', $d['line_id'])->where('co_id', $co)->firstOrFail();
        abort_if(RmBooking::whereKey($line->booking_id)->value('stat_confirm'), 422, t('Booking is confirmed; use Back to Draft first.'));
        MaterialPlanning::engine()->pick($line, (int) $d['material_id']);
        MaterialPlanning::changed();

        return redirect($booking($co))->with('v2_flash', Dictionary::t('Item picked and booking recalculated.'));
    })->name('pick');

    // Pre-Booking sejak ProCost terbit (B-01..B-03): hitung ulang, Pick Item, Hold / Release Hold.
    $prebooking = fn (?int $id = null) => route('v2.screen', 'material-prebooking').($id ? '?id='.$id : '');

    Route::post('/prebooking/run', function () use ($prebooking) {
        $n = MaterialPlanning::engine()->runPre();
        MaterialPlanning::changed();

        return redirect($prebooking())->with('v2_flash', tf('Pre-bookings recalculated: :n.', ['n' => (string) $n]));
    })->name('prebooking.run');

    Route::post('/prebooking/{id}/act', function (Request $r, int $id) use ($prebooking) {
        $b = PreBookings::find($id) ?? abort(404);
        $engine = MaterialPlanning::engine();
        $msg = match ($r->input('action')) {
            'hold' => (function () use ($engine, $b, $r) {
                $engine->hold($b, (string) $r->input('reason', ''));

                return t('Stock is on hold. The hold ends by itself on the date shown.');
            })(),
            'release' => (function () use ($engine, $b) {
                $engine->releaseHold($b);

                return t('Hold released. The stock is available again.');
            })(),
            default => abort(422),
        };
        MaterialPlanning::changed();

        return redirect($prebooking($id))->with('v2_flash', $msg);
    })->whereNumber('id')->name('prebooking.act');

    Route::post('/prebooking/{id}/pick', function (Request $r, int $id) use ($prebooking) {
        $b = PreBookings::find($id) ?? abort(404);
        $d = $r->validate(['line_id' => ['required', 'integer'], 'material_id' => ['required', 'integer', 'exists:tm2mst.mat_raw,material_id']], [], ['material_id' => t('Stock Item'), 'line_id' => t('Line')]);
        abort_if($b->stat_confirm, 422, t('This booking is on hold; release the hold first.'));
        MaterialPlanning::engine()->pick(RmBookingD::where('booking_detail_id', $d['line_id'])->where('booking_id', $b->booking_id)->firstOrFail(), (int) $d['material_id']);
        MaterialPlanning::changed();

        return redirect($prebooking($id))->with('v2_flash', t('Item picked and booking recalculated.'));
    })->whereNumber('id')->name('prebooking.pick');

    Route::post('/create-pr', function (Request $r) {
        $d = $r->validate(['material_ids' => ['required', 'array', 'min:1'], 'material_ids.*' => ['integer']], [], ['material_ids' => t('Short materials')]);
        $made = MaterialPlanning::createPr($d['material_ids']);

        return redirect(route('v2.screen', 'purchase-requisition').'?id='.$made[0]->pr_id)
            ->with('v2_flash', str_replace(':n', (string) count($made), Dictionary::t(':n Purchase Requisition(s) created as Draft from Material Planning.')));
    })->name('create-pr');
});
