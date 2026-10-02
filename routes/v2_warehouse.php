<?php

use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use App\Services\Inventory\BinGenerator;
use App\Support\V2\Data\Labels;
use App\Support\V2\Data\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Inventory & Warehouse v2 (docs/warehouselayout_tier1.md): peta gudang, area + bin, Stock Transfer, label QR / barcode.
// File route sendiri (dimuat routes/web.php sesudah v2.php) supaya tidak bercampur dengan pekerjaan modul lain di v2.php.
// Logika: App\Support\V2\Data\{Warehouse, StockTransfer, Labels} + App\Services\Inventory\{StockTransferService, BinGenerator}.
Route::prefix('v2/warehouse')->name('v2.wh.')->middleware('module:INV')->group(function () {
    $map = fn (?int $wh = null, array $q = []) => route('v2.screen', 'warehouse-map').'?'.http_build_query(array_filter(['wh' => $wh] + $q));
    $transfers = fn () => route('v2.screen', 'stock-transfer');
    $open = fn (int $id) => redirect($transfers().'?id='.$id);
    $login = fn () => Auth::user()->login;

    // ------------------------------------------------------------ Peta: tata letak area (Edit Layout)
    Route::post('/zone-move', function (Request $r) use ($login) {
        $d = $r->validate(['zone' => ['required', 'integer'], 'x' => ['required', 'numeric', 'min:0'], 'y' => ['required', 'numeric', 'min:0'],
            'w' => ['required', 'numeric', 'min:1'], 'h' => ['required', 'numeric', 'min:1']]);
        $zone = WhZone::query()->with('warehouse')->findOrFail((int) $d['zone']);
        [$maxX, $maxY] = $zone->warehouse->extent();
        // Area tidak boleh keluar dari kotak pembungkus gedung.
        $w = min((float) $d['w'], $maxX);
        $h = min((float) $d['h'], $maxY);
        $zone->update(['map_x' => min((float) $d['x'], $maxX - $w), 'map_y' => min((float) $d['y'], $maxY - $h), 'map_w' => $w, 'map_h' => $h, 'updated_by' => $login()]);

        return response()->json(['ok' => true]);
    })->name('zone-move');

    // ------------------------------------------------------------ Bin: blokir / buka blokir / transfer isi bin
    Route::post('/bin/{id}/block', function (Request $r, int $id) use ($login) {
        $reason = mb_substr(trim((string) $r->input('reason', '')), 0, 255);
        if ($reason === '') {
            return response()->json(['message' => t('Fill in the reason for blocking the bin.')], 422);
        }
        WhLoc::query()->findOrFail($id)->update(['is_usable' => false, 'block_reason' => $reason, 'updated_by' => $login()]);

        return response()->json(['ok' => true]);
    })->whereNumber('id')->name('bin.block');

    Route::post('/bin/{id}/unblock', function (int $id) use ($login) {
        WhLoc::query()->findOrFail($id)->update(['is_usable' => true, 'block_reason' => null, 'updated_by' => $login()]);

        return response()->json(['ok' => true]);
    })->whereNumber('id')->name('bin.unblock');

    Route::post('/bin/{id}/transfer', function (int $id) use ($transfers) {
        $bin = WhLoc::query()->findOrFail($id);
        $t = StockTransfer::createFromBin($bin);
        session()->flash('v2_flash', str_replace(':bin', $bin->location_code, t('Draft created with everything in :bin. Choose the destination bins, then Confirm.')));

        return response()->json(['redirect' => $transfers().'?id='.$t->id_transfer]);
    })->whereNumber('id')->name('bin.transfer');

    // ------------------------------------------------------------ Pembuat bin (per area)
    Route::post('/bins/generate', function (Request $r) use ($map, $login) {
        $d = $r->validate(['zone_id' => ['required', 'integer'], 'rows_from' => ['nullable', 'integer', 'min:1', 'max:99'], 'rows_to' => ['nullable', 'integer', 'min:1', 'max:99'],
            'cols_from' => ['nullable', 'integer', 'min:1', 'max:99'], 'cols_to' => ['nullable', 'integer', 'min:1', 'max:99'], 'levels' => ['nullable', 'integer', 'min:1', 'max:9'],
            'capacity_kg' => ['nullable', 'numeric', 'min:0'], 'capacity_pallet' => ['nullable', 'integer', 'min:0']], [], ['zone_id' => 'Storage area']);
        $zone = WhZone::query()->with('warehouse')->findOrFail((int) $d['zone_id']);
        $res = BinGenerator::generate($zone, ['rows_from' => $d['rows_from'] ?? 1, 'rows_to' => $d['rows_to'] ?? ($d['rows_from'] ?? 1), 'cols_from' => $d['cols_from'] ?? 1,
            'cols_to' => $d['cols_to'] ?? ($d['cols_from'] ?? 1), 'levels' => $d['levels'] ?? 1, 'capacity_kg' => $d['capacity_kg'] ?? null, 'capacity_pallet' => $d['capacity_pallet'] ?? null], $login());

        return redirect($map($zone->warehouse_id, ['floor' => $zone->floor_no]))->with('v2_flash', str_replace([':n', ':s', ':zone'], [(string) $res['created'], (string) $res['skipped'], $zone->zone_code],
            t(':n bins created in :zone (:s already existed).')));
    })->name('bins.generate');

    // ------------------------------------------------------------ Stock Transfer: action = save | confirm | cancel | delete
    Route::post('/transfer/create', function (Request $r) use ($open) {
        $d = $r->validate(['from_warehouse_id' => ['required', 'integer'], 'to_warehouse_id' => ['required', 'integer'], 'reason' => ['nullable', 'string']],
            [], ['from_warehouse_id' => 'From warehouse', 'to_warehouse_id' => 'To warehouse']);

        return $open(StockTransfer::create((int) $d['from_warehouse_id'], (int) $d['to_warehouse_id'], $d['reason'] ?? null)->id_transfer)
            ->with('v2_flash', t('Draft created. Add the items to move, then Confirm.'));
    })->name('transfer.create');

    Route::post('/transfer/{id}/save', function (Request $r, int $id) use ($transfers, $open) {
        $doc = StockTransfer::find($id) ?? abort(404);
        $action = (string) $r->input('action', 'save');
        if ($action === 'delete') {
            StockTransfer::delete($doc);

            return redirect($transfers())->with('v2_flash', t('Draft deleted.'));
        }
        if ($action === 'cancel') {
            $err = StockTransfer::act($doc, 'cancel', (string) $r->input('_reason', ''));

            return $err ? $open($id)->withErrors(['cancel' => $err]) : $open($id)->with('v2_flash', t('Transfer cancelled. The stock is back in the source bins.'));
        }
        $r->validate(['transfer_date' => ['nullable', 'date', 'before_or_equal:today']], [], ['transfer_date' => 'Date']);
        StockTransfer::save($doc, $r->all());
        if ($action === 'confirm') {
            $err = StockTransfer::act($doc->refresh(), 'confirm');

            return $err ? $open($id)->withErrors(['confirm' => $err]) : $open($id)->with('v2_flash', t('Confirmed. The stock has moved; the transfer is ready to print.'));
        }

        return $open($id)->with('v2_flash', t('Saved.'));
    })->whereNumber('id')->name('transfer.save');

    // ------------------------------------------------------------ Label: catat riwayat lalu buka lembar cetak
    Route::post('/labels/print', function (Request $r) {
        $d = $r->validate(['type' => ['required', 'in:location,item'], 'keys' => ['required', 'array', 'min:1', 'max:'.Labels::MAX_PER_PRINT], 'keys.*' => ['string', 'max:60'],
            'size' => ['required', 'in:sm,md,lg'], 'copies' => ['nullable', 'integer', 'min:1', 'max:20'], 'back' => ['nullable', 'string', 'max:40']],
            ['keys.required' => t('Choose at least one label to print.')], ['keys' => 'Labels']);
        $copies = (int) ($d['copies'] ?? 1);
        Labels::log($d['type'], $d['keys'], $copies, $d['size']);

        return redirect()->route('v2.wh.labels.sheet', ['type' => $d['type'], 'keys' => implode('~', $d['keys']), 'size' => $d['size'], 'copies' => $copies, 'back' => $d['back'] ?? null]);
    })->name('labels.print');

    Route::get('/labels/sheet', function (Request $r) {
        $type = $r->query('type') === 'item' ? 'item' : 'location';
        $size = in_array($r->query('size'), ['sm', 'md', 'lg'], true) ? $r->query('size') : 'md';
        $back = in_array($r->query('back'), ['warehouse-map', 'stock-transfer', 'scan-track', 'label-item'], true) ? $r->query('back') : ($type === 'item' ? 'label-item' : 'label-location');

        return view('v2.label-sheet', ['labels' => Labels::sheet($type, array_filter(explode('~', (string) $r->query('keys'))), (int) $r->query('copies', 1)),
            'size' => $size, 'type' => $type, 'back' => route('v2.screen', $back)]);
    })->name('labels.sheet');

    // Satu label bin langsung dari peta / lacak (tanpa layar pilih): dicatat lalu lembar cetak.
    Route::get('/bin/{id}/label', function (int $id) {
        $bin = WhLoc::query()->findOrFail($id);
        Labels::log('location', [$bin->location_id], 1, 'md');

        return redirect()->route('v2.wh.labels.sheet', ['type' => 'location', 'keys' => $bin->location_id, 'size' => 'md', 'copies' => 1, 'back' => 'warehouse-map']);
    })->whereNumber('id')->name('bin.label');
});
