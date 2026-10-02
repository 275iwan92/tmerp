<?php

namespace App\Support\V2\Data;

use App\Models\Inventory\InvLabelPrint;
use App\Models\Inventory\InvMovement;
use App\Models\Inventory\InvTransfer;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\WhLoc;
use App\Services\Inventory\WarehouseStock;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

// Label gudang + lacak lewat QR / barcode (user 2026-10-02: "Facility for QR Code, Labelling"). Isi x-dl::label untuk
// label LOKASI (bin: kode, area, kapasitas) dan label ITEM (satu posisi stok: item, bin, qty), riwayat cetaknya, dan
// pencarian kode hasil pindai. Isi QR = alamat halaman lacak (HP cukup membuka kameranya); barcode 1D = kodenya saja.
final class Labels
{
    public const MAX_PER_PRINT = 300;

    // ---------------------------------------------------------------- isi label

    /** @param  array<string, mixed>|null  $summary  ringkasan WarehouseStock::binSummaries untuk bin ini (boleh kosong) */
    public static function location(WhLoc $b, ?array $summary = null): array
    {
        $zone = $b->zone;
        $where = array_filter([$zone ? $zone->zone_name : null, $zone ? t('Floor').' '.$zone->floor_no : null, $b->warehouse?->warehouse_name]);

        return [
            'title' => 'Location', 'code' => $b->location_code, 'name' => implode(' · ', $where),
            'lines' => array_values(array_filter([
                $b->capacity_kg ? ['label' => 'Capacity', 'value' => Locale::number($b->capacity_kg).' kg'] : null,
                $b->capacity_pallet ? ['label' => 'Pallets', 'value' => (string) $b->capacity_pallet] : null,
                $summary && $summary['used_kg'] > 0 ? ['label' => 'In use', 'value' => Locale::number($summary['used_kg']).' kg'.($summary['pct'] !== null ? ' · '.Locale::number($summary['pct']).'%' : '')] : null,
                $b->is_usable ? null : ['label' => 'Status', 'value' => t('Blocked')],
            ])),
            'qr' => Warehouse::trackUrl('LOC', $b->location_code), 'barcode' => $b->location_code,
            'tone' => ($summary['dead'] ?? false) ? 'danger' : null, 'footer' => ($summary['dead'] ?? false) ? t('Holds dead stock') : null,
        ];
    }

    /** @param  array<string, mixed>  $p  satu posisi stok WarehouseStock::positions */
    public static function item(array $p): array
    {
        return [
            'title' => WarehouseStock::CLASSES[$p['class']], 'code' => (string) $p['code'], 'name' => (string) $p['name'],
            'lines' => array_values(array_filter([
                ['label' => 'Qty', 'value' => Locale::decimal($p['qty'], 2).' '.$p['uom']],
                ['label' => 'Bin', 'value' => (string) $p['location_code']],
                $p['weight_kg'] !== null ? ['label' => 'Weight', 'value' => Locale::number($p['weight_kg']).' kg'] : null,
                ['label' => 'Last move', 'value' => $p['last_movement'] ? (string) Locale::d($p['last_movement']) : t('Never moved')],
            ])),
            'qr' => Warehouse::trackUrl('ITM', $p['class'].':'.$p['code']), 'barcode' => (string) $p['code'],
            'tone' => $p['dead'] ? 'danger' : null, 'footer' => $p['dead'] ? t('DEAD STOCK') : null,
        ];
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
            ->mapWithKeys(fn (array $p) => [$p['key'] => $p['code'].' · '.$p['name'].' | '.$p['location_code'].' | '.Locale::decimal($p['qty'], 2).' '.$p['uom'].($p['dead'] ? ' | '.t('DEAD STOCK') : '')])->all();
    }

    public static function positionsOf(int $warehouseId): Collection
    {
        return WarehouseStock::positions(WhLoc::query()->where('warehouse_id', $warehouseId)->pluck('location_id')->all());
    }

    // ---------------------------------------------------------------- lembar cetak + riwayat

    // Label yang dicetak: $type = location | item, $keys = id bin / kunci posisi. Kembalian = isi x-dl::label (sebanyak copies).
    /** @return list<array<string, mixed>> */
    public static function sheet(string $type, array $keys, int $copies): array
    {
        $copies = max(1, min(20, $copies));
        if ($type === 'location') {
            $bins = WhLoc::query()->with(['zone', 'warehouse'])->whereKey(array_map('intval', $keys))->orderBy('location_code')->get();
            $sum = WarehouseStock::binSummaries($bins);
            $labels = $bins->map(fn (WhLoc $b) => self::location($b, $sum[$b->location_id]))->all();
        } else {
            $parsed = array_values(array_filter(array_map(fn ($k) => WarehouseStock::parseKey((string) $k), $keys)));
            $pos = WarehouseStock::positions(array_values(array_unique(array_column($parsed, 'location_id'))))->keyBy('key');
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

    // Catat satu permintaan cetak (satu baris per label unik) -> laporan Label Print History.
    public static function log(string $type, array $keys, int $copies, string $size): int
    {
        $login = Auth::user()->login;
        $rows = [];
        if ($type === 'location') {
            foreach (WhLoc::query()->with('zone')->whereKey(array_map('intval', $keys))->get() as $b) {
                $rows[] = ['label_type' => 'LOCATION', 'ref_code' => $b->location_code, 'ref_name' => $b->zone?->zone_name, 'ref_id' => $b->location_id];
            }
        } else {
            $parsed = array_values(array_filter(array_map(fn ($k) => WarehouseStock::parseKey((string) $k), $keys)));
            $pos = WarehouseStock::positions(array_values(array_unique(array_column($parsed, 'location_id'))))->keyBy('key');
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

    // Kode hasil pindai: alamat QR (…?code=LOC:xxx), "LOC:kode", "ITM:RM:kode", atau kode polos (bin / item / nomor transfer).
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
        if (preg_match('/^(LOC|ITM):(.+)$/i', $raw, $m)) {
            return ['kind' => strtoupper($m[1]), 'code' => trim($m[2])];
        }

        return ['kind' => 'ANY', 'code' => $raw];
    }

    // Hasil lacak: ['type' => bin|item|transfer|none, ...].
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
        if ($q['kind'] === 'ANY' && ($t = InvTransfer::query()->where('transfer_no', $code)->first())) {
            return ['type' => 'transfer', 'code' => $code, 'transfer' => $t];
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

                return ['type' => 'item', 'code' => $itemCode, 'class' => $c, 'item' => $item, 'positions' => $positions, 'moves' => $moves];
            }
        }

        return ['type' => 'none', 'code' => $code];
    }
}
