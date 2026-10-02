<?php

namespace App\Support\V2\Data;

use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Services\Inventory\WarehouseStock;
use Devlib\Locale;
use Illuminate\Support\Collection;

// Peta gudang v2 (docs/warehouselayout_tier1.md): menyusun isi x-dl::wh-map dari database. Gudang = gedung (garis luar +
// lantai), area = wh_zone (persegi dalam meter), bin = wh_loc; isi, kapasitas dan dead stock dari WarehouseStock.
// Letak bin di dalam area dihitung di sini (lajur ke kanan, slot ke bawah; rak bertingkat = satu sel berisi beberapa bin)
// kecuali bin itu punya posisi sendiri (map_x / map_y).
final class Warehouse
{
    // Ruang di dalam area untuk judul (atas) dan tepi (meter).
    private const PAD = 0.4;

    private const HEAD = 1.0;

    private const GAP = 0.25;

    /** @return array<int, string> id => "A10 · Warehouse A10", gedung yang punya denah lebih dulu */
    public static function options(bool $withLayoutOnly = false): array
    {
        return WhWarehouse::query()->where('activate', true)->orderBy('warehouse_code')->get()
            ->filter(fn (WhWarehouse $w) => ! $withLayoutOnly || $w->hasLayout())
            ->sortByDesc(fn (WhWarehouse $w) => $w->hasLayout())
            ->mapWithKeys(fn (WhWarehouse $w) => [$w->id_warehouse => $w->warehouse_code.' · '.$w->warehouse_name])->all();
    }

    public static function current(?int $id): ?WhWarehouse
    {
        $list = WhWarehouse::query()->where('activate', true)->orderBy('warehouse_code')->get();

        return ($id ? $list->firstWhere('id_warehouse', $id) : null) ?? $list->first(fn (WhWarehouse $w) => $w->hasLayout()) ?? $list->first();
    }

    // Alamat yang dibuka saat QR label dipindai (halaman lacak).
    public static function trackUrl(string $kind, string $code): string
    {
        return route('v2.screen', 'scan-track').'?code='.rawurlencode($kind.':'.$code);
    }

    /** @return array{width: float, height: float, outline: list<array>, floors: list<array>, bins: array<int, array>, unassigned: int, totals: array} */
    public static function map(WhWarehouse $w): array
    {
        $zones = $w->zones()->where('activate', true)->get();
        $bins = WhLoc::query()->where('warehouse_id', $w->id_warehouse)->where('activate', true)->orderBy('row_code')->orderBy('col_code')->orderBy('stack_code')->get();
        $sum = WarehouseStock::binSummaries($bins);
        [$width, $height] = $w->extent();

        $floors = [];
        for ($n = 1; $n <= $w->floors(); $n++) {
            $floors[] = ['no' => $n, 'label' => t('Floor').' '.$n, 'zones' => $zones->where('floor_no', $n)->map(fn (WhZone $z) => [
                'id' => $z->id_zone, 'code' => $z->zone_code, 'name' => $z->zone_name, 'tone' => WhZone::TONES[$z->zone_type] ?? 'accent',
                'x' => $z->map_x, 'y' => $z->map_y, 'w' => $z->map_w, 'h' => $z->map_h, 'cells' => self::cells($z, $bins->where('zone_id', $z->id_zone)->values()),
            ])->values()->all()];
        }

        $zoneOf = $zones->keyBy('id_zone');
        $out = [];
        foreach ($bins->whereNotNull('zone_id') as $b) {
            $out[$b->location_id] = self::bin($b, $sum[$b->location_id], $zoneOf->get($b->zone_id));
        }
        $all = collect($sum);

        return ['width' => $width, 'height' => $height, 'outline' => $w->outlinePoints(), 'floors' => $floors, 'bins' => $out,
            'unassigned' => $bins->whereNull('zone_id')->count(),
            'totals' => ['bins' => $bins->count(), 'used_kg' => (float) $all->sum('used_kg'), 'capacity_kg' => (float) $all->sum('capacity_kg'),
                'dead_bins' => $all->where('dead', true)->count(), 'area' => $w->floorArea() * $w->floors()]];
    }

    // Sel di denah untuk satu area. Kembalian: [['x', 'y', 'w', 'h', 'label', 'bins' => [location_id..]], ...] (meter, mutlak).
    private static function cells(WhZone $z, Collection $bins): array
    {
        if ($bins->isEmpty()) {
            return [];
        }
        $innerW = max(0.6, $z->map_w - 2 * self::PAD);
        $innerH = max(0.6, $z->map_h - self::HEAD - self::PAD);
        $left = $z->map_x + self::PAD;
        $top = $z->map_y + self::HEAD;
        if ($z->storage_mode === 'AREA' || $bins->count() === 1) {
            return [['x' => $left, 'y' => $top, 'w' => $innerW, 'h' => $innerH, 'label' => $z->zone_code, 'bins' => $bins->pluck('location_id')->all()]];
        }
        $rows = $bins->groupBy('row_code')->sortKeys();
        $cols = $bins->pluck('col_code')->unique()->sort()->values();
        $cw = max(0.4, ($innerW - self::GAP * ($rows->count() - 1)) / $rows->count());
        $ch = max(0.4, ($innerH - self::GAP * ($cols->count() - 1)) / max($cols->count(), 1));
        $cells = [];
        $i = 0;
        foreach ($rows as $rowCode => $rowBins) {
            $short = str_starts_with((string) $rowCode, $z->zone_code.'-') ? substr((string) $rowCode, strlen($z->zone_code) + 1) : (string) $rowCode;
            foreach ($rowBins->groupBy('col_code') as $colCode => $stack) {
                $first = $stack->first();
                $cells[] = ['x' => $first->map_x ?? $left + $i * ($cw + self::GAP), 'y' => $first->map_y ?? $top + $cols->search($colCode) * ($ch + self::GAP),
                    'w' => $cw, 'h' => $ch, 'label' => $short.'-'.$colCode, 'bins' => $stack->sortBy('stack_code')->pluck('location_id')->values()->all()];
            }
            $i++;
        }

        return $cells;
    }

    // Isi panel satu bin (teks sudah diformat & diterjemahkan di server).
    private static function bin(WhLoc $b, array $s, ?WhZone $z): array
    {
        $notes = array_filter([
            $b->is_usable ? null : ($b->block_reason ?: t('Blocked')),
            $s['unknown_weight'] ? str_replace(':n', (string) $s['unknown_weight'], t(':n items have no weight data and are not counted in the capacity.')) : null,
        ]);

        return [
            'code' => $b->location_code, 'title' => trim(($z ? $z->zone_name.' · ' : '').t('Floor').' '.($z->floor_no ?? 1)),
            'level' => $z?->storage_mode === 'RACK' ? t('Level').' '.$b->stack_code : null, 'status' => $s['status'], 'dead' => $s['dead'],
            'capacity' => self::capacityText($s['used_kg'], $s['capacity_kg']), 'pct' => $s['pct'], 'capacityLabel' => self::capacityLabel($b),
            'note' => $notes ? implode(' ', $notes) : null, 'qr' => self::trackUrl('LOC', $b->location_code),
            'items' => array_map(fn (array $i) => ['code' => $i['code'], 'name' => $i['name'], 'qty' => Locale::decimal($i['qty'], 2).' '.$i['uom'],
                'idle' => self::idleText($i['idle_days']), 'dead' => $i['dead']], $s['items']),
        ];
    }

    public static function capacityText(float $used, ?float $capacity): ?string
    {
        return $capacity ? Locale::number($used).' / '.Locale::number($capacity).' kg' : ($used > 0 ? Locale::number($used).' kg' : null);
    }

    // Teks kapasitas di label bin: "Max 3,000 kg · 4 pallets".
    public static function capacityLabel(WhLoc $b): ?string
    {
        $parts = array_filter([$b->capacity_kg ? Locale::number($b->capacity_kg).' kg' : null,
            $b->capacity_pallet ? $b->capacity_pallet.' '.t('pallets') : null]);

        return $parts ? t('Max').' '.implode(' · ', $parts) : null;
    }

    public static function idleText(?int $days): string
    {
        return $days === null ? t('Never moved') : $days.' '.t('days');
    }

    // Semua bin satu gudang untuk pilihan (label + sisa kapasitas). $usableOnly = hanya yang boleh menerima barang.
    /** @return array<int, string> location_id => "A10-PPR-L05-03 · free 933 kg of 3,000" */
    public static function binOptions(int $warehouseId, bool $usableOnly = true): array
    {
        $bins = WhLoc::query()->where('warehouse_id', $warehouseId)->where('activate', true)->when($usableOnly, fn ($q) => $q->where('is_usable', true))
            ->orderBy('location_code')->get();
        $sum = WarehouseStock::binSummaries($bins);

        return $bins->mapWithKeys(function (WhLoc $b) use ($sum) {
            $s = $sum[$b->location_id];
            $free = $s['capacity_kg'] ? str_replace([':free', ':cap'], [Locale::number(max(0, $s['capacity_kg'] - $s['used_kg'])), Locale::number($s['capacity_kg'])], t('free :free kg of :cap')) : t('no capacity set');

            return [$b->location_id => $b->location_code.' · '.$free.($s['status'] === 'EMPTY' ? ' · '.t('empty') : '')];
        })->all();
    }
}
