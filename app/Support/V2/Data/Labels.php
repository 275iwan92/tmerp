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

        return [
            'kind' => 'pallet', 'title' => 'Pallet', 'color' => Label::monthColor($arrived), 'mark' => self::monthMark($arrived),
            'code' => $p->pallet_no, 'name' => $p->item_code.' · '.$p->item_name, 'highlight' => Locale::decimal($p->qty, 2).' '.$p->uom_code,
            'lines' => array_values(array_filter([
                ['label' => 'Arrived', 'value' => $arrived ? (string) Locale::d($arrived) : '—'],
                ['label' => 'Supplier pallet', 'value' => $p->supplier_pallet_code ?: '—'],
                $p->lot_no ? ['label' => 'Lot', 'value' => $p->lot_no] : null,
                $p->ref_doc_no ? ['label' => 'Receipt', 'value' => $p->ref_doc_no] : null,
                $binCode ? ['label' => 'Bin', 'value' => $binCode] : null,
                $p->expiry_date ? ['label' => 'Expiry', 'value' => (string) Locale::d($p->expiry_date)] : null,
                (float) $p->qty !== (float) $p->qty_initial ? ['label' => 'At arrival', 'value' => Locale::decimal($p->qty_initial, 2).' '.$p->uom_code] : null,
                ['label' => 'Label no.', 'value' => (string) max(1, (int) $p->label_count)],
            ])),
            'tags' => array_values(array_filter([$p->isActive() ? null : ['label' => 'Closed', 'tone' => 'danger'], self::expiryTag($state)])),
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
            $label['lines'] = array_values(array_filter($label['lines'], fn (array $l) => in_array($l['label'], ['Arrived', 'Supplier pallet', 'Expiry'], true)));
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
}
