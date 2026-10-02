<?php

use App\Models\Inventory\InvPallet;
use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use App\Services\Inventory\BinGenerator;
use App\Services\Inventory\PalletService;
use App\Support\V2\Data\Labels;
use App\Support\V2\Data\StockTransfer;
use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Inventory & Warehouse v2 (docs/warehouselayout_tier1.md): peta gudang, area + bin, Stock Transfer, label QR / barcode.
// File route sendiri (dimuat routes/web.php sesudah v2.php) supaya tidak bercampur dengan pekerjaan modul lain di v2.php.
// Logika: App\Support\V2\Data\{Warehouse, StockTransfer, Labels} + App\Services\Inventory\{StockTransferService, PalletService, BinGenerator}.
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
        if ($action === 'duplicate') {
            return $open(StockTransfer::duplicate($doc)->id_transfer)->with('v2_flash', t('Draft created as a copy. Check the lines, then Confirm.'));
        }
        if ($action === 'cancel') {
            $err = StockTransfer::act($doc, 'cancel', (string) $r->input('_reason', ''));

            return $err ? $open($id)->withErrors(['cancel' => $err]) : $open($id)->with('v2_flash', $doc->stat_confirm
                ? t('Transfer cancelled. The stock is back in the source bins.') : t('Transfer cancelled. No stock had moved yet.'));
        }
        // Keputusan pemeriksa (approval Stock Transfer aktif): Reject dan Hold wajib alasan.
        if (in_array($action, ['approve', 'reject', 'hold'], true)) {
            $note = trim((string) $r->input('_reason', ''));
            $err = $action !== 'approve' && $note === '' ? t('Fill in the reason.') : StockTransfer::decide($doc, $action, $note);

            return $err ? $open($id)->withErrors(['approval' => $err]) : $open($id)->with('v2_flash', t(['approve' => 'Approved.', 'reject' => 'Rejected.', 'hold' => 'On hold.'][$action]));
        }
        $r->validate(['transfer_date' => ['nullable', 'date', 'before_or_equal:today']], [], ['transfer_date' => 'Date']);
        StockTransfer::save($doc, $r->all());
        if ($action === 'confirm') {
            $err = StockTransfer::act($doc->refresh(), 'confirm');
            if ($err) {
                return $open($id)->withErrors(['confirm' => $err]);
            }

            return $open($id)->with('v2_flash', $doc->refresh()->status() === 'SUBMITTED'
                ? t('Confirmed and sent for approval. The stock moves after the last approval.') : t('Confirmed. The stock has moved; the transfer is ready to print.'));
        }

        return $open($id)->with('v2_flash', t('Saved.'));
    })->whereNumber('id')->name('transfer.save');

    // ------------------------------------------------------------ Label: catat riwayat lalu buka lembar cetak
    // back = layar asal (slug) + rid = id dokumen asal (mis. penerimaan barang) supaya tombol Back kembali ke sana.
    $sheet = fn (string $type, array $keys, string $size, int $copies, string $paper, ?string $back, ?int $rid = null) => redirect()->route('v2.wh.labels.sheet',
        array_filter(['type' => $type, 'keys' => implode('~', $keys), 'size' => $size, 'copies' => $copies, 'paper' => $paper, 'back' => $back, 'rid' => $rid]));

    Route::post('/labels/print', function (Request $r) use ($sheet, $login) {
        $d = $r->validate(['type' => ['required', 'in:location,item,pallet'], 'keys' => ['required', 'array', 'min:1', 'max:'.Labels::MAX_PER_PRINT], 'keys.*' => ['string', 'max:60'],
            'size' => ['required', 'in:sm,md,lg'], 'paper' => ['nullable', 'in:roll,a4'], 'copies' => ['nullable', 'integer', 'min:1', 'max:20'], 'back' => ['nullable', 'string', 'max:40'],
            'rid' => ['nullable', 'integer']],
            ['keys.required' => t('Choose at least one label to print.')], ['keys' => 'Labels']);
        $copies = (int) ($d['copies'] ?? 1);
        Labels::log($d['type'], $d['keys'], $copies, $d['size']);
        if ($d['type'] === 'pallet') {
            PalletService::printed(InvPallet::query()->whereKey(array_map('intval', $d['keys']))->get(), $login());
        }

        return $sheet($d['type'], $d['keys'], $d['size'], $copies, $d['paper'] ?? 'a4', $d['back'] ?? null, isset($d['rid']) ? (int) $d['rid'] : null);
    })->name('labels.print');

    Route::get('/labels/sheet', function (Request $r) {
        $type = in_array($r->query('type'), ['item', 'pallet'], true) ? $r->query('type') : 'location';
        $size = in_array($r->query('size'), ['sm', 'md', 'lg'], true) ? $r->query('size') : 'md';
        $default = ['item' => 'label-item', 'pallet' => 'label-pallet', 'location' => 'label-location'][$type];
        $back = in_array($r->query('back'), ['warehouse-map', 'stock-transfer', 'scan-track', 'label-item', 'label-pallet', 'label-location', 'purchase-receive'], true) ? $r->query('back') : $default;
        $rid = $r->integer('rid');
        $query = $rid ? '?'.($back === 'purchase-receive' || $back === 'stock-transfer' ? 'id' : 'receipt').'='.$rid : '';

        return view('v2.label-sheet', ['labels' => Labels::sheet($type, array_filter(explode('~', (string) $r->query('keys'))), (int) $r->query('copies', 1)),
            'size' => $size, 'type' => $type, 'paper' => $r->query('paper') === 'roll' ? 'roll' : 'a4', 'back' => route('v2.screen', $back).$query]);
    })->name('labels.sheet');

    // ------------------------------------------------------------ Palet: label kedatangan, label ulang sesudah dipakai
    // Label kedatangan: palet baru untuk barang yang belum berlabel di satu posisi stok, lalu langsung ke lembar cetak.
    Route::post('/pallet/create', function (Request $r) use ($sheet, $login) {
        $d = $r->validate(['position' => ['required', 'string', 'max:60'], 'lines' => ['required', 'array', 'min:1', 'max:60'], 'arrived_at' => ['nullable', 'date', 'before_or_equal:today'],
            'ref_doc_no' => ['nullable', 'string', 'max:50'], 'notes' => ['nullable', 'string', 'max:255'], 'size' => ['required', 'in:sm,md,lg'], 'paper' => ['nullable', 'in:roll,a4'],
            'rid' => ['nullable', 'integer'], 'back' => ['nullable', 'string', 'max:40']], [], ['position' => 'Stock', 'lines' => 'Pallets', 'arrived_at' => 'Arrival date']);
        $remove = (array) $r->input('remove', []);
        $rows = collect($d['lines'])->reject(fn ($row, $k) => isset($remove[$k]))->map(fn ($row) => ['qty' => (float) (Locale::input($row['qty'] ?? 0) ?? 0),
            'supplier_pallet_code' => $row['supplier_pallet_code'] ?? null, 'lot_no' => $row['lot_no'] ?? null, 'expiry_date' => $row['expiry_date'] ?? null])->values()->all();
        try {
            $pallets = PalletService::create($d['position'], $rows, ['arrived_at' => $d['arrived_at'] ?? null, 'ref_doc_no' => $d['ref_doc_no'] ?? null, 'notes' => $d['notes'] ?? null], $login());
        } catch (RuntimeException $e) {
            return back()->withErrors(['pallet' => $e->getMessage()])->withInput();
        }
        $keys = $pallets->pluck('id_pallet')->map(fn ($id) => (string) $id)->all();
        Labels::log('pallet', $keys, 1, $d['size']);

        return $sheet('pallet', $keys, $d['size'], 1, $d['paper'] ?? 'a4', $d['back'] ?? 'label-pallet', isset($d['rid']) ? (int) $d['rid'] : null);
    })->name('pallet.create');

    // Label ulang: qty sisa menggantikan qty lama (0 = palet ditutup, tanpa label).
    Route::post('/pallet/relabel', function (Request $r) use ($sheet, $login) {
        $d = $r->validate(['pallet_id' => ['required', 'integer'], 'remaining' => ['required'], 'note' => ['nullable', 'string', 'max:255'], 'size' => ['nullable', 'in:sm,md,lg'],
            'paper' => ['nullable', 'in:roll,a4']], [], ['pallet_id' => 'Pallet', 'remaining' => 'Remaining quantity']);
        $pallet = InvPallet::query()->findOrFail((int) $d['pallet_id']);
        try {
            PalletService::relabel($pallet, (float) (Locale::input($d['remaining']) ?? -1), $login(), $d['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['pallet' => $e->getMessage()])->withInput();
        }
        if (! $pallet->isActive()) {
            return back()->with('v2_flash', str_replace(':no', $pallet->pallet_no, t('Pallet :no is empty and closed.')));
        }
        Labels::log('pallet', [(string) $pallet->id_pallet], 1, $d['size'] ?? 'lg');

        return $sheet('pallet', [(string) $pallet->id_pallet], $d['size'] ?? 'lg', 1, $d['paper'] ?? 'a4', 'label-pallet');
    })->name('pallet.relabel');

    Route::post('/pallet/{id}/transfer', function (int $id) use ($open) {
        $pallet = InvPallet::query()->where('status', 'ACTIVE')->findOrFail($id);

        return $open(StockTransfer::createFromPallet($pallet)->id_transfer)
            ->with('v2_flash', str_replace(':no', $pallet->pallet_no, t('Draft created for pallet :no. Choose the destination bin, then Confirm.')));
    })->whereNumber('id')->name('pallet.transfer');

    // Satu label bin langsung dari peta / lacak (tanpa layar pilih): dicatat lalu lembar cetak.
    Route::get('/bin/{id}/label', function (int $id) {
        $bin = WhLoc::query()->findOrFail($id);
        Labels::log('location', [$bin->location_id], 1, 'md');

        return redirect()->route('v2.wh.labels.sheet', ['type' => 'location', 'keys' => $bin->location_id, 'size' => 'md', 'copies' => 1, 'paper' => 'a4', 'back' => 'warehouse-map']);
    })->whereNumber('id')->name('bin.label');
});
