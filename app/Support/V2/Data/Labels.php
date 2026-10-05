<?php

namespace App\Support\V2\Data;

use App\Models\Inventory\InvLabelPrint;
use App\Models\Inventory\InvMovement;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvTransfer;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\WarehouseStock;
use Devlib\Components\Label;
use Devlib\Components\LabelSheet;
use Devlib\Components\WhMap;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

// Label gudang + lacak lewat QR / barcode (user 2026-10-02). Tiga label yang sengaja berbeda:
//   LOKASI (bin: kode, area, kapasitas berat + volume + luas)      pita = warna jenis area
//   ITEM   (satu posisi stok: item, bin, qty, tanggal datang)      pita = kelas stok (RM biru, SFG kuning tua, FG hijau)
//   PALET  (satu palet: nomor palet, qty, tanggal datang, kode palet supplier, lot, kedaluwarsa)  pita = bulan datang
// Semua punya QR (isi = alamat halaman lacak, HP cukup membuka kameranya) dan barcode 1D (kodenya saja).
// Tag: Dead stock, Expired, Expires soon. Riwayat cetak = tm2inv.inv_label_print.
final class Labels
{
    public const MAX_PER_PRINT = 300;

    public const CLASS_COLORS = ['RM' => 'accent', 'SFG' => 'amber', 'FG' => 'green'];

    // Warna pita label lokasi per jenis area (palet warna x-dl::label).
    private const ZONE_COLORS = ['RECEIVING' => 'violet', 'QC_HOLD' => 'yellow', 'STORAGE' => 'accent', 'STAGING' => 'teal', 'SHIPPING' => 'green',
        'REJECT' => 'danger', 'CONSIGN' => 'pink', 'TOOLING' => 'navy'];

    // ---------------------------------------------------------------- isi label

    /** @param  array<string, mixed>|null  $summary  ringkasan WarehouseStock::binSummaries untuk bin ini (boleh kosong) */
    public static function location(WhLoc $b, ?array $summary = null): array
    {
        $zone = $b->zone;
        $where = array_filter([$zone ? $zone->zone_name : null, $zone ? t('Floor').' '.$zone->floor_no : null, $b->warehouse?->warehouse_name]);
        $dead = (bool) ($summary['dead'] ?? false);

        return [
            'kind' => 'location', 'title' => 'Location', 'color' => self::ZONE_COLORS[$zone?->zone_type] ?? 'neutral', 'mark' => $zone ? t(WhZone::TYPES[$zone->zone_type] ?? $zone->zone_type) : null,
            'code' => $b->location_code, 'name' => implode(' · ', $where), 'highlight' => null,
            'lines' => array_values(array_filter([
                $b->capacity_kg ? ['label' => 'Max weight', 'value' => Locale::number($b->capacity_kg).' kg'] : null,
                ($summary['capacity_m3'] ?? null) ? ['label' => 'Volume', 'value' => Locale::decimal($summary['capacity_m3'], 2).' m³'] : null,
                ($summary['area_m2'] ?? null) ? ['label' => 'Floor area', 'value' => Locale::decimal($summary['area_m2'], 2).' m²'
                    .(($summary['height_m'] ?? null) ? ' × '.Locale::decimal($summary['height_m'], 2).' m' : '')] : null,
                $b->capacity_pallet ? ['label' => 'Pallets', 'value' => (string) $b->capacity_pallet] : null,
                $summary && $summary['pct'] !== null && $summary['items'] ? ['label' => 'In use', 'value' => Locale::number($summary['pct']).'%'] : null,
            ])),
            'tags' => array_values(array_filter([
                $b->is_usable ? null : ['label' => 'Blocked', 'tone' => 'danger'],
                $dead ? ['label' => 'Holds dead stock', 'tone' => 'danger'] : null,
                self::expiryTag($summary['expiry_state'] ?? null),
            ])),
            'qr' => Warehouse::trackUrl('LOC', $b->location_code), 'barcode' => $b->location_code, 'tone' => $dead ? 'danger' : null, 'footer' => null,
        ];
    }

    /** @param  array<string, mixed>  $p  satu posisi stok (WarehouseStock::positions, boleh dengan data palet PalletService::worklist) */
    public static function item(array $p): array
    {
        $pallets = $p['pallets'] ?? collect();
        $expiry = $pallets->pluck('expiry_date')->filter()->min();
        $state = self::worstExpiry($pallets);
        $lots = $pallets->pluck('lot_no')->filter()->unique()->take(2)->implode(', ');

        return [
            'kind' => 'item', 'title' => 'Item', 'color' => self::CLASS_COLORS[$p['class']] ?? 'neutral', 'mark' => t(WarehouseStock::CLASSES[$p['class']]),
            'code' => (string) $p['code'], 'name' => (string) $p['name'], 'highlight' => Locale::decimal($p['qty'], 2).' '.$p['uom'],
            'lines' => array_values(array_filter([
                ['label' => 'Bin', 'value' => (string) $p['location_code']],
                ['label' => 'Arrived', 'value' => $p['arrived'] ? (string) Locale::d($p['arrived']) : '—'],
                $lots !== '' ? ['label' => 'Lot', 'value' => $lots] : null,
                $expiry ? ['label' => 'Expiry', 'value' => (string) Locale::d($expiry)] : null,
                $p['weight_kg'] !== null ? ['label' => 'Weight', 'value' => Locale::number($p['weight_kg']).' kg'] : null,
            ])),
            'tags' => array_values(array_filter([$p['dead'] ? ['label' => 'Dead stock', 'tone' => 'danger'] : null, self::expiryTag($state)])),
            'qr' => Warehouse::trackUrl('ITM', $p['class'].':'.$p['code']), 'barcode' => (string) $p['code'],
            'tone' => $p['dead'] || $state === 'EXPIRED' ? 'danger' : null, 'footer' => null,
        ];
    }

    // Label palet. $binCode = kode bin palet sekarang.
    public static function pallet(InvPallet $p, ?string $binCode = null): array
    {
        $state = $p->expiryState(WarehouseStock::expirySoonDays());
        $arrived = $p->arrived_at?->toDateString();
        // Alamat lengkap = bin (X - Y) + nomor tumpukan (user 2026-10-03); 1 = paling bawah.
        $where = $binCode ? $binCode.($p->isActive() && $p->stack_no ? ' · '.t('Stack').' '.$p->stack_no : '') : null;

        return [
            'kind' => 'pallet', 'title' => $p->isPack() ? 'Pack' : 'Pallet', 'color' => Label::monthColor($arrived), 'mark' => self::monthMark($arrived),
            'code' => $p->pallet_no, 'name' => $p->item_code.' · '.$p->item_name, 'highlight' => Locale::decimal($p->qty, 2).' '.$p->uom_code,
            'lines' => array_values(array_filter([
                ['label' => 'Arrived', 'value' => $arrived ? (string) Locale::d($arrived) : '—'],
                ['label' => 'Supplier pallet', 'value' => $p->supplier_pallet_code ?: '—'],
                $p->lot_no ? ['label' => 'Lot', 'value' => $p->lot_no] : null,
                $p->ref_doc_no ? ['label' => 'Receipt', 'value' => $p->ref_doc_no] : null,
                $where ? ['label' => 'Bin', 'value' => $where] : null,
                $p->expiry_date ? ['label' => 'Expiry', 'value' => (string) Locale::d($p->expiry_date)] : null,
                (float) $p->qty !== (float) $p->qty_initial ? ['label' => 'At arrival', 'value' => Locale::decimal($p->qty_initial, 2).' '.$p->uom_code] : null,
                ['label' => 'Label no.', 'value' => (string) max(1, (int) $p->label_count)],
            ])),
            'tags' => array_values(array_filter([$p->isActive() ? null : ['label' => 'Closed', 'tone' => 'danger'], $p->isPack() ? ['label' => 'Remnant', 'tone' => null] : null, self::expiryTag($state)])),
            'qr' => Warehouse::trackUrl('PLT', $p->pallet_no), 'barcode' => $p->pallet_no,
            'tone' => $state === 'EXPIRED' ? 'danger' : null, 'footer' => t('After every use: print a new label with the remaining quantity.'),
        ];
    }

    // Isi label disesuaikan dengan ukurannya supaya tidak terpotong: label palet dirancang untuk 100 x 150 mm; di ukuran
    // lebih kecil hanya baris terpenting yang ikut. Ukuran kecil (60 x 40 mm) memuat paling banyak tiga baris.
    public static function fit(array $label, string $size): array
    {
        if ($size === 'lg') {
            return $label;
        }
        if (($label['kind'] ?? null) === 'pallet') {
            // Label kecil: pak sisa harus bisa dicari -> bin + nomor tumpukan ikut; palet penuh -> kode palet supplier.
            $keep = ($label['title'] ?? null) === 'Pack' ? ['Bin', 'Arrived', 'Expiry'] : ['Arrived', 'Supplier pallet', 'Expiry'];
            $label['lines'] = array_values(array_filter($label['lines'], fn (array $l) => in_array($l['label'], $keep, true)));
            $label['footer'] = null;
        }
        if ($size === 'sm') {
            $label['lines'] = array_slice($label['lines'], 0, 3);
            $label['footer'] = null;
        }

        return $label;
    }

    private static function expiryTag(?string $state): ?array
    {
        return match ($state) {
            'EXPIRED' => ['label' => 'Expired', 'tone' => 'danger'], 'SOON' => ['label' => 'Expires soon', 'tone' => 'warn'], default => null,
        };
    }

    public static function worstExpiry(Collection $pallets): ?string
    {
        $states = $pallets->map(fn (InvPallet $p) => $p->expiryState(WarehouseStock::expirySoonDays()))->filter();

        return $states->contains('EXPIRED') ? 'EXPIRED' : ($states->contains('SOON') ? 'SOON' : null);
    }

    // "OKT 2026" dari tanggal datang (nama bulan mengikuti bahasa tampilan).
    public static function monthMark(?string $date): ?string
    {
        return $date ? mb_strtoupper(str_replace('-', ' ', substr((string) Locale::d($date), 3))) : null;
    }

    // Arti warna pita untuk x-dl::label-guide.
    /** @return list<array{color: string, label: string}> */
    public static function colorLegend(string $type): array
    {
        if ($type === 'pallet') {
            return collect(range(1, 12))->map(fn (int $m) => ['color' => Label::MONTH_COLORS[$m], 'label' => mb_strtoupper(substr((string) Locale::d(sprintf('2026-%02d-01', $m)), 3, -5))])->all();
        }
        if ($type === 'item') {
            return collect(self::CLASS_COLORS)->map(fn (string $c, string $k) => ['color' => $c, 'label' => t(WarehouseStock::CLASSES[$k])])->values()->all();
        }

        return collect(self::ZONE_COLORS)->map(fn (string $c, string $k) => ['color' => $c, 'label' => t(WhZone::TYPES[$k])])->values()->all();
    }

    // ---------------------------------------------------------------- pilihan di layar cetak

    /** @return array<int, string> location_id => "A10-PPR-L05-03 · Plano Paper" */
    public static function binOptions(int $warehouseId): array
    {
        return WhLoc::query()->with('zone')->where('warehouse_id', $warehouseId)->where('activate', true)->orderBy('location_code')->get()
            ->mapWithKeys(fn (WhLoc $b) => [$b->location_id => $b->location_code.($b->zone ? ' · '.$b->zone->zone_name : '')])->all();
    }

    /** @return array<string, string> kunci posisi => "PYJ31 · IVORY 300 79 X 109 | A5-PPR-L01-01 | 6,000 LBR" */
    public static function positionOptions(int $warehouseId): array
    {
        return self::positionsOf($warehouseId)->sortBy(fn (array $p) => $p['location_code'].' '.$p['code'])
            ->mapWithKeys(fn (array $p) => [$p['key'] => self::positionText($p)])->all();
    }

    public static function positionText(array $p): string
    {
        return $p['code'].' · '.$p['name'].' | '.$p['location_code'].' | '.Locale::decimal($p['qty'], 2).' '.$p['uom'].($p['dead'] ? ' | '.t('DEAD STOCK') : '');
    }

    public static function positionsOf(int $warehouseId): Collection
    {
        return WarehouseStock::positions(self::binIds($warehouseId));
    }

    /** @return list<int> */
    public static function binIds(int $warehouseId): array
    {
        return WhLoc::query()->where('warehouse_id', $warehouseId)->pluck('location_id')->all();
    }

    // ---------------------------------------------------------------- lembar cetak + riwayat

    // Label yang dicetak: $type = location | item | pallet, $keys = id bin / kunci posisi / id palet.
    // Kembalian = isi x-dl::label (sebanyak copies).
    /** @return list<array<string, mixed>> */
    public static function sheet(string $type, array $keys, int $copies): array
    {
        $copies = max(1, min(20, $copies));
        if ($type === 'location') {
            $bins = WhLoc::query()->with(['zone', 'warehouse'])->whereKey(array_map('intval', $keys))->orderBy('location_code')->get();
            $sum = WarehouseStock::binSummaries($bins);
            $labels = $bins->map(fn (WhLoc $b) => self::location($b, $sum[$b->location_id]))->all();
        } elseif ($type === 'pallet') {
            $pallets = InvPallet::query()->whereKey(array_map('intval', $keys))->orderBy('pallet_no')->get();
            $bins = WhLoc::query()->whereKey($pallets->pluck('location_id')->unique()->all())->pluck('location_code', 'location_id');
            $labels = $pallets->map(fn (InvPallet $p) => self::pallet($p, $bins[$p->location_id] ?? null))->all();
        } else {
            $pos = self::positionsByKey($keys);
            $labels = array_values(array_filter(array_map(fn ($k) => $pos->has((string) $k) ? self::item($pos[(string) $k]) : null, $keys)));
        }
        $out = [];
        foreach ($labels as $l) {
            for ($i = 0; $i < $copies && count($out) < self::MAX_PER_PRINT; $i++) {
                $out[] = $l;
            }
        }

        return $out;
    }

    // Posisi stok (dengan data paletnya) untuk kunci-kunci yang diminta.
    private static function positionsByKey(array $keys): Collection
    {
        $parsed = array_values(array_filter(array_map(fn ($k) => WarehouseStock::parseKey((string) $k), $keys)));

        return PalletService::worklist(array_values(array_unique(array_column($parsed, 'location_id'))))->keyBy('key');
    }

    // Catat satu permintaan cetak (satu baris per label unik) -> laporan Label Print History.
    public static function log(string $type, array $keys, int $copies, string $size): int
    {
        $login = Auth::user()->login;
        $rows = [];
        if ($type === 'location') {
            foreach (WhLoc::query()->with('zone')->whereKey(array_map('intval', $keys))->get() as $b) {
                $rows[] = ['label_type' => 'LOCATION', 'ref_code' => $b->location_code, 'ref_name' => $b->zone?->zone_name, 'ref_id' => $b->location_id];
            }
        } elseif ($type === 'pallet') {
            $pallets = InvPallet::query()->whereKey(array_map('intval', $keys))->get();
            $bins = WhLoc::query()->whereKey($pallets->pluck('location_id')->unique()->all())->pluck('location_code', 'location_id');
            foreach ($pallets as $p) {
                $rows[] = ['label_type' => 'PALLET', 'ref_code' => $p->pallet_no, 'ref_name' => mb_substr($p->item_code.' · '.$p->item_name.' @ '.($bins[$p->location_id] ?? '?'), 0, 255),
                    'stock_class' => $p->stock_class, 'ref_id' => $p->id_pallet, 'lot_no' => $p->lot_no, 'qty' => $p->qty, 'uom_code' => $p->uom_code];
            }
        } else {
            $pos = self::positionsByKey($keys);
            foreach ($keys as $k) {
                if ($p = $pos->get((string) $k)) {
                    $rows[] = ['label_type' => 'ITEM', 'ref_code' => mb_substr((string) $p['code'], 0, 100), 'ref_name' => mb_substr($p['name'].' @ '.$p['location_code'], 0, 255),
                        'stock_class' => $p['class'], 'ref_id' => $p['item_id'], 'qty' => $p['qty'], 'uom_code' => $p['uom'] ?: null];
                }
            }
        }
        foreach ($rows as $r) {
            InvLabelPrint::create($r + ['copies' => max(1, min(20, $copies)), 'label_size' => $size, 'printed_at' => now(), 'printed_by' => $login]);
        }

        return count($rows);
    }

    // ---------------------------------------------------------------- lacak (hasil pindai)

    // Kode hasil pindai: alamat QR (…?code=LOC:xxx), "LOC:kode", "ITM:RM:kode", "PLT:nomor", atau kode polos
    // (bin / nomor palet / nomor transfer / item).
    /** @return array{kind: string, code: string}|null */
    public static function parse(string $raw): ?array
    {
        $raw = trim($raw);
        if (preg_match('/[?&]code=([^&#]+)/', $raw, $m)) {
            $raw = rawurldecode($m[1]);
        }
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(LOC|ITM|PLT):(.+)$/i', $raw, $m)) {
            return ['kind' => strtoupper($m[1]), 'code' => trim($m[2])];
        }

        return ['kind' => 'ANY', 'code' => $raw];
    }

    // Hasil lacak: ['type' => bin|item|pallet|transfer|none, ...].
    public static function track(string $raw): array
    {
        $q = self::parse($raw);
        if (! $q) {
            return ['type' => 'none', 'code' => ''];
        }
        $code = $q['code'];
        if (in_array($q['kind'], ['LOC', 'ANY'], true) && ($bin = WhLoc::query()->with(['zone', 'warehouse'])->where('location_code', $code)->first())) {
            $sum = WarehouseStock::binSummaries(collect([$bin]))[$bin->location_id];

            return ['type' => 'bin', 'code' => $code, 'bin' => $bin, 'summary' => $sum, 'label' => self::location($bin, $sum)];
        }
        if (in_array($q['kind'], ['PLT', 'ANY'], true) && ($pallet = InvPallet::query()->with('logs')->where('pallet_no', $code)->first())) {
            $bin = WhLoc::query()->with(['zone', 'warehouse'])->find($pallet->location_id);

            return ['type' => 'pallet', 'code' => $code, 'pallet' => $pallet, 'bin' => $bin, 'label' => self::pallet($pallet, $bin?->location_code)];
        }
        if ($q['kind'] === 'ANY' && ($t = InvTransfer::query()->where('transfer_no', $code)->first())) {
            return ['type' => 'transfer', 'code' => $code, 'transfer' => $t];
        }
        if ($q['kind'] === 'PLT') {
            return ['type' => 'none', 'code' => $code];
        }
        // ITM:RM:kode -> kelas disebut; kode polos -> dicari di ketiga master.
        [$class, $itemCode] = $q['kind'] === 'ITM' && preg_match('/^(RM|SFG|FG):(.+)$/', $code, $m) ? [$m[1], $m[2]] : [null, $code];
        foreach ($class ? [$class] : array_keys(WarehouseStock::CLASSES) as $c) {
            $item = match ($c) {
                'RM' => MatRaw::withoutGlobalScopes()->where('material_code', $itemCode)->first(['material_id as id', 'material_code as code', 'material_name as name']),
                'SFG' => ProdPart::withoutGlobalScopes()->where('part_code', $itemCode)->first(['id_part as id', 'part_code as code', 'part_name as name']),
                default => ProdProduct::withoutGlobalScopes()->where('product_code', $itemCode)->first(['id_product as id', 'product_code as code', 'product_name as name']),
            };
            if ($item) {
                $positions = WarehouseStock::positions(null, [$c => [(int) $item->id]])->sortBy('location_code')->values();
                $moves = InvMovement::query()->where('stock_class', $c)->where('item_id', $item->id)->orderByDesc('doc_date')->orderByDesc('doc_id')->limit(15)->get();
                $pallets = InvPallet::query()->where('status', 'ACTIVE')->where('stock_class', $c)->where('item_id', $item->id)->orderBy('arrived_at')->get();

                return ['type' => 'item', 'code' => $itemCode, 'class' => $c, 'item' => $item, 'positions' => $positions, 'moves' => $moves, 'pallets' => $pallets];
            }
        }

        return ['type' => 'none', 'code' => $code];
    }

    // Isi objek form khusus x-mp::inv.label-print untuk layar Print Location Label: gedung -> area (mencentang semua bin
    // area itu) -> bin, ukuran, kertas, jumlah lembar. 'empty' terisi = belum ada gedung, objek hanya menampilkan pesan itu.
    public static function locationForm(string $home): array
    {
        $wh = Warehouse::current(request()->integer('wh') ?: null);
        $form = ['title' => 'Print Location Label', 'icon' => 'fa-qrcode', 'layout' => 'row', 'notice' => null, 'filter' => null, 'nothing' => null, 'print' => null,
            'description' => 'A label for every bin: its code, storage area, capacity (weight, volume, floor area), a QR code for the phone camera and a barcode for the scanner. The colour band shows the kind of storage area.',
            'legend' => self::colorLegend('location'), 'empty' => $wh ? null : ['No warehouse yet', 'Add a building and its bins first.']];
        if (! $wh) {
            return $form;
        }
        $zoneId = request()->integer('zone') ?: null;
        $form['filter'] = ['action' => $home, 'fields' => [
            ['wh', 'Building', Warehouse::options(), $wh->id_warehouse, ['tm2mst.wh_warehouse']],
            ['zone', 'Storage area (ticks all its bins)', $wh->zones()->where('activate', true)->get()
                ->mapWithKeys(fn ($z) => [$z->id_zone => $z->zone_code.' · '.$z->zone_name.' · '.t('Floor').' '.$z->floor_no])->all(), $zoneId, ['tm2mst.wh_zone']],
        ]];
        $form['print'] = self::printPart(['type' => 'location'], 'Bins', self::binOptions($wh->id_warehouse),
            // Area dipilih = semua bin area itu langsung tercentang.
            $zoneId ? WhLoc::query()->where('zone_id', $zoneId)->where('activate', true)->pluck('location_id')->map(fn ($v) => (string) $v)->all() : [],
            ['tm2mst.wh_loc'], null);

        return $form;
    }

    // Isi objek yang sama untuk layar Print Item Label: posisi stok satu gedung, atau (?receipt=) item satu penerimaan barang
    // yang sudah tercentang semua.
    public static function itemForm(string $home): array
    {
        $pv = Pallets::receipt(request()->integer('receipt') ?: null);
        $wh = Warehouse::current(request()->integer('wh') ?: null);
        $form = ['title' => 'Print Item Label', 'icon' => 'fa-tag', 'layout' => 'stack', 'notice' => null, 'filter' => null, 'nothing' => null, 'print' => null,
            'description' => 'A label for stock that is in a bin now: item code and name, quantity, bin, arrival date, lot and expiry date, a QR code and a barcode. The colour band shows the stock class; a red stripe means dead stock or expired.',
            'legend' => self::colorLegend('item'), 'empty' => $wh || $pv ? null : ['No warehouse yet', 'Add a building and its bins first.']];
        if (! $wh && ! $pv) {
            return $form;
        }
        if ($pv) {
            $options = collect(PalletService::receiptLines($pv))->pluck('position')->filter()->keyBy('key')->map(fn (array $p) => self::positionText($p))->all();
            $form['notice'] = [
                'message' => str_replace([':no', ':date'], [$pv->label(), (string) Locale::d($pv->i_doc_date)], t('Item labels for goods receipt :no, received :date. The items of this receipt are already ticked.')),
                'buttons' => [['back', 'Back to Receipt', route('v2.screen', 'purchase-receive').'?id='.$pv->pv_id],
                    ['fa-pallet', 'Pallet Labels of this Receipt', route('v2.screen', 'label-pallet').'?receipt='.$pv->pv_id]],
            ];
        } else {
            $options = self::positionOptions($wh->id_warehouse);
            $form['filter'] = ['action' => $home, 'fields' => [['wh', 'Building', Warehouse::options(), $wh->id_warehouse, ['tm2mst.wh_warehouse']]]];
        }
        if (! $options) {
            $form['nothing'] = $pv ? 'This receipt put no stock into a bin, so there is nothing to label.' : 'This building holds no stock, so there is nothing to label.';

            return $form;
        }
        $form['print'] = self::printPart(['type' => 'item', 'back' => 'label-item'] + ($pv ? ['rid' => $pv->pv_id] : []), 'Stock (item | bin | on hand)', $options,
            $pv ? array_keys($options) : [], ['tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand'],
            'One label per pack or stack: type how many packs carry the same label.');

        return $form;
    }

    // Isi objek form khusus x-mp::inv.scan-track (layar Track via QR / Barcode): hasil lacak self::track() dijadikan satu
    // array siap tampil. 'result' null = belum ada kode; type: none | transfer | pallet | bin | item.
    public static function trackDesk(string $home): array
    {
        $code = trim((string) request('code', ''));
        $res = $code !== '' ? self::track($code) : null;
        $mapUrl = fn ($bin) => route('v2.screen', 'warehouse-map').'?wh='.$bin->warehouse_id.'&bin='.$bin->location_id;
        $out = ['home' => $home, 'result' => null];
        if (! $res) {
            return $out;
        }
        $out['result'] = match ($res['type']) {
            'none' => ['type' => 'none', 'message' => str_replace(':code', $res['code'], t('Nothing found for ":code". Check the code, or look it up in the Warehouse Map.'))],
            'transfer' => ['type' => 'transfer', 'title' => t('Stock Transfer').' · '.$res['transfer']->label(), 'openUrl' => route('v2.screen', 'stock-transfer').'?id='.$res['transfer']->id_transfer],
            'pallet' => (function () use ($res, $mapUrl) {
                $p = $res['pallet'];
                $bin = $res['bin'];

                return ['type' => 'pallet', 'id' => $p->id_pallet, 'title' => t($p->isPack() ? 'Pack' : 'Pallet').' · '.$p->pallet_no, 'description' => $p->item_code.' · '.$p->item_name,
                    'moveAction' => $p->isActive() ? route('v2.wh.pallet.transfer', $p->id_pallet) : null,
                    'relabelUrl' => $p->isActive() ? route('v2.screen', 'label-pallet').'?pallet='.$p->id_pallet : null,
                    'mapUrl' => $bin ? $mapUrl($bin) : null, 'label' => self::fit($res['label'], 'md'),
                    'facts' => [
                        ['Bin', $bin?->location_code ?? '—', ['tm2inv.inv_pallet.location_id']],
                        ['Stack No.', $p->isActive() && $p->stack_no ? $p->stack_no.' · '.t($p->isPack() ? 'Remnant' : 'Pallet') : '—', ['tm2inv.inv_pallet.stack_no', 'tm2inv.inv_pallet.unit_kind']],
                        ['Quantity now / at arrival', Locale::decimal($p->qty, 2).' / '.Locale::decimal($p->qty_initial, 2).' '.$p->uom_code, ['tm2inv.inv_pallet.qty']],
                        ['Supplier Pallet Code', $p->supplier_pallet_code ?: '—', ['tm2inv.inv_pallet.supplier_pallet_code']],
                        ['Expiry Date', $p->expiry_date ? Locale::d($p->expiry_date) : '—', ['tm2inv.inv_pallet.expiry_date']],
                    ],
                    'rows' => Pallets::history($p),
                    'actionTones' => array_combine(array_map(fn (string $a) => t($a), Pallets::ACTIONS), ['success', 'warn', 'info', 'neutral', 'violet'])];
            })(),
            'bin' => (function () use ($res, $mapUrl) {
                $bin = $res['bin'];
                $s = $res['summary'];
                $facts = [
                    ['Weight', Warehouse::capacityText($s['used_kg'], $s['capacity_kg']) ?? '—', ['tm2mst.wh_loc.capacity_kg']],
                    ['Volume', Warehouse::volumeText($s['used_m3'], $s['capacity_m3']) ?? '—', ['tm2mst.wh_zone (area, stack height)']],
                    ['Status', $s['dead'] ? t('Dead stock') : t(WhMap::STATUS[$s['status']]), []],
                ];
                if (! $bin->is_usable) {
                    $facts[] = ['Blocked because', $bin->block_reason ?? '—', ['tm2mst.wh_loc.block_reason']];
                }

                return ['type' => 'bin', 'id' => $bin->location_id, 'title' => t('Bin').' · '.$bin->location_code, 'description' => (string) $res['label']['name'],
                    'mapUrl' => $mapUrl($bin), 'labelUrl' => route('v2.wh.bin.label', $bin->location_id), 'label' => self::fit($res['label'], 'md'), 'facts' => $facts,
                    'rows' => array_map(fn (array $i) => ['id' => $i['key'], 'item' => $i['name'], 'code' => $i['code'], 'class' => $i['class'], 'qty' => $i['qty'], 'unit' => $i['uom'], 'weight' => $i['weight_kg'],
                        'arrived' => $i['arrived'], 'expiry' => $i['expiry'], 'pallets' => $i['pallet_count'],
                        'last' => $i['last_movement'], 'idle' => $i['idle_days'], 'state' => $i['dead'] ? 'Dead stock' : ($i['expiry_state'] === 'EXPIRED' ? 'Expired' : ($i['expiry_state'] === 'SOON' ? 'Expires soon' : 'OK')),
                        'tone' => $i['dead'] || $i['expiry_state'] === 'EXPIRED' ? 'danger' : ($i['expiry_state'] === 'SOON' ? 'yellow' : null)], $s['items'])];
            })(),
            'item' => (function () use ($res) {
                $item = $res['item'];
                $pos = $res['positions'];

                return ['type' => 'item', 'id' => $res['class'].'-'.$item->id, 'title' => $item->code.' · '.$item->name,
                    'description' => str_replace([':class', ':n', ':qty'], [t(WarehouseStock::CLASSES[$res['class']]), (string) $pos->count(), Locale::decimal($pos->sum('qty'), 2)], t(':class · in :n bin(s) · :qty on hand in total')),
                    'rows' => $pos->map(fn (array $p) => ['id' => $p['key'], 'bin' => $p['location_code'], 'qty' => $p['qty'], 'unit' => $p['uom'], 'weight' => $p['weight_kg'], 'value' => $p['value'],
                        'arrived' => $p['arrived'], 'last' => $p['last_movement'], 'idle' => $p['idle_days'], 'state' => $p['dead'] ? 'Dead stock' : 'OK', 'tone' => $p['dead'] ? 'danger' : null])->all(),
                    'pallets' => $res['pallets']->isNotEmpty() ? Pallets::rows($res['pallets'], route('v2.screen', 'label-pallet')) : null, 'expiryTones' => Pallets::expiryTones(),
                    'moves' => $res['moves']->map(fn ($m) => ['id' => $m->doc_id, 'date' => $m->doc_date?->toDateString(), 'doc_no' => $m->doc_no, 'move' => $m->doc_type, 'warehouse' => $m->warehouse_name,
                        'bin' => $m->location_name, 'in' => (float) $m->qty_in, 'out' => (float) $m->qty_out, 'adj' => (float) $m->qty_adj, 'balance' => (float) $m->qty_eb])->all()];
            })(),
        };

        return $out;
    }

    private static function printPart(array $hidden, string $keysLabel, array $options, array $picked, array $sources, ?string $copiesHint): array
    {
        return ['action' => route('v2.wh.labels.print'), 'hidden' => $hidden, 'keysLabel' => $keysLabel, 'options' => $options, 'picked' => $picked,
            'sources' => $sources, 'copiesHint' => $copiesHint, 'sizes' => Label::SIZES, 'papers' => array_map(fn (string $p) => t($p), LabelSheet::PAPERS),
            'historyUrl' => route('v2.screen', 'inv-label-history')];
    }
}
