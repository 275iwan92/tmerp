<?php

use App\Models\Inventory\WhPickList;
use App\Services\Inventory\PickListRefused;
use App\Services\Inventory\PickListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Inventory > Warehouse > Pick List (W-05, B-12). File route sendiri (dimuat routes/web.php) supaya terpisah dari v2_warehouse.php.
// Logika: App\Services\Inventory\PickListService; layar: resources/views/v2/screens/wh-pick-list.blade.php; data: App\Support\V2\Data\PickLists.
Route::prefix('v2/warehouse/pick')->name('v2.wh.pick.')->middleware('module:INV')->group(function () {
    $screen = fn (?int $id = null) => route('v2.screen', 'wh-pick-list').($id ? '?id='.$id : '');
    $login = fn () => Auth::user()->login;

    Route::post('/create', function (Request $r) use ($screen, $login) {
        $d = $r->validate(['wo_id' => ['required', 'integer'], 'deliver_to_location_id' => ['nullable', 'integer']], [], ['wo_id' => 'Work Order', 'deliver_to_location_id' => 'Deliver To']);
        try {
            $list = PickListService::create((int) $d['wo_id'], $login(), $d['deliver_to_location_id'] ?? null);
        } catch (PickListRefused $e) {
            return redirect($screen().'?new=1')->withErrors(['create' => $e->getMessage()])->withInput();
        }

        return redirect($screen($list->id_pick_list))->with('v2_flash', t('Pick list created. Scan each pallet and its bin, then Confirm Pick.'));
    })->name('create');

    // action = confirm | reallocate | cancel (alasan di _reason)
    Route::post('/{id}/act', function (Request $r, int $id) use ($screen, $login) {
        $list = WhPickList::query()->findOrFail($id);
        try {
            switch ((string) $r->input('action')) {
                case 'confirm':
                    $res = PickListService::confirm($list, $login());
                    $msg = tf('Pick confirmed. Stock Out :no posted to the work order.', ['no' => implode(', ', $res['docs'])]).($res['warning'] ? ' '.$res['warning'] : '');
                    break;
                case 'reallocate':
                    PickListService::reallocate($list, $login());
                    $msg = t('Allocation recalculated.');
                    break;
                case 'cancel':
                    PickListService::cancel($list, (string) $r->input('_reason', ''), $login());
                    $msg = t('Pick list cancelled. The allocation is released.');
                    break;
                default:
                    abort(422);
            }
        } catch (PickListRefused $e) {
            return redirect($screen($id))->withErrors(['pick' => $e->getMessage()]);
        }

        return redirect($screen($id))->with('v2_flash', $msg);
    })->whereNumber('id')->name('act');

    Route::post('/{id}/scan', function (Request $r, int $id) use ($screen, $login) {
        $d = $r->validate(['code' => ['required', 'string', 'max:200']], [], ['code' => 'Scanned Code']);
        try {
            $res = PickListService::scan(WhPickList::query()->findOrFail($id), $d['code'], $login());
        } catch (PickListRefused $e) {
            return redirect($screen($id))->withErrors(['scan' => $e->getMessage()]);
        }

        return redirect($screen($id))->with('v2_flash', $res['kind'] === 'pallet' ? t('Pallet scanned. Now scan its bin.') : t('Bin scanned. Line picked.'));
    })->whereNumber('id')->name('scan');
});
