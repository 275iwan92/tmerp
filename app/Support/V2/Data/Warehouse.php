<?php

namespace App\Support\V2\Data;

use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Services\Inventory\PalletService;
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
        // Area virtual (subkon, In Transit, Loss) tidak punya tempat fisik: tidak tampil di peta (v2.01).
        $zones = $w->zones()->where('activate', true)->where('is_virtual', false)->get();
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
            'unassigned' => $bins->whereNull('zone_id')->count(), 'capacity' => self::floorCapacity($w, $zones, $bins, $sum),
            'totals' => ['bins' => $bins->count(), 'used_kg' => (float) $all->sum('used_kg'), 'capacity_kg' => (float) $all->sum('capacity_kg'),
                'used_m3' => (float) $all->sum('used_m3'), 'capacity_m3' => (float) $all->sum('capacity_m3'),
                'dead_bins' => $all->where('dead', true)->count(), 'expiry_bins' => $all->whereNotNull('expiry_state')->count(), 'area' => $w->floorArea() * $w->floors()]];
    }

    // Kapasitas per lantai (user 2026-10-02: "kapasitas dalam luasan dan tinggi gedung, termasuk lantai 1, 2 dan 3"):
    // luas lantai, tinggi, volume ruang; luas area penyimpanan dan berapa persen lantai; volume simpan (luas area x
    // tinggi tumpuk); isi sekarang (m3 dan kg). Tinggi kosong = volume lantai itu tidak dihitung.
    /** @return list<array<string, mixed>> */
    private static function floorCapacity(WhWarehouse $w, Collection $zones, Collection $bins, array $sum): array
    {
        $rows = [];
        for ($n = 1; $n <= $w->floors(); $n++) {
            $fz = $zones->where('floor_no', $n);
            $fb = $bins->whereIn('zone_id', $fz->pluck('id_zone')->all());
            $s = collect($sum)->only($fb->pluck('location_id')->all());
            $area = $w->floorArea();
            $height = $w->floorHeight($n);
            $storage = (float) $fz->sum(fn (WhZone $z) => $z->areaM2());
            $capM3 = (float) $s->sum('capacity_m3');
            $usedM3 = (float) $s->sum('used_m3');
            $capKg = (float) $s->sum('capacity_kg');
            $usedKg = (float) $s->sum('used_kg');
            $rows[] = ['id' => $n, 'floor' => t('Floor').' '.$n, 'area' => $area, 'height' => $height, 'room' => $height ? round($area * $height, 1) : null,
                'storage' => round($storage, 1), 'storage_pct' => $area > 0 ? round($storage / $area * 100, 1) : null, 'areas' => $fz->count(), 'bins' => $fb->count(),
                'cap_m3' => $capM3 ? round($capM3, 1) : null, 'used_m3' => round($usedM3, 2), 'pct_m3' => $capM3 > 0 ? round($usedM3 / $capM3 * 100, 1) : null,
                'cap_kg' => $capKg ?: null, 'used_kg' => round($usedKg), 'pct_kg' => $capKg > 0 ? round($usedKg / $capKg * 100, 1) : null,
                'in_use' => $s->filter(fn (array $b) => $b['items'] !== [])->count()];
        }

        return $rows;
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
            $s['unknown_volume'] && $s['capacity_m3'] ? str_replace(':n', (string) $s['unknown_volume'], t(':n items have no size data and are not counted in the volume.')) : null,
        ]);
        $size = $s['area_m2'] ? Locale::decimal($s['area_m2'], 2).' m²'.($s['height_m'] ? ' × '.Locale::decimal($s['height_m'], 2).' m' : '') : null;
        $caps = array_values(array_filter([
            $s['capacity_kg'] || $s['used_kg'] > 0 ? ['label' => t('Weight'), 'value' => self::capacityText($s['used_kg'], $s['capacity_kg']), 'pct' => $s['pct_kg']] : null,
            $s['capacity_m3'] ? ['label' => t('Volume'), 'value' => self::volumeText($s['used_m3'], $s['capacity_m3']), 'pct' => $s['pct_m3']] : null,
            $size ? ['label' => t('Floor area × height'), 'value' => $size, 'pct' => null] : null,
            $s['unit_count'] ? ['label' => t('Stack'), 'value' => self::stackText($s), 'pct' => null] : null,
            $s['stack_height_m'] > 0 ? ['label' => t('Stack height'), 'value' => Locale::decimal($s['stack_height_m'], 2).($s['height_m'] ? ' / '.Locale::decimal($s['height_m'], 2) : '').' m', 'pct' => $s['stack_pct']] : null,
        ]));
        $buried = collect($s['stack'])->max('buried');
        $notes = array_merge($notes, array_filter([
            $buried ? str_replace(':n', (string) $buried, t('The unit that should be used first is under :n other unit(s). Move those first.')) : null,
            $s['over_stack'] ? str_replace([':n', ':max'], [(string) $s['pallet_count'], (string) $s['max_stack']], t(':n pallets are stacked here; this area allows :max.')) : null,
            $s['unrecorded'] && $s['unit_count'] ? t('Some stock in this bin has no label yet, so its place in the stack is not known.') : null,
        ]));
        $deadKeys = collect($s['items'])->where('dead', true)->pluck('key')->flip();

        return [
            'count' => $s['unit_count'], 'remnant' => $s['remnant'], 'buried' => $s['buried'],
            'stack' => array_map(fn (array $u) => ['id' => $u['id'], 'no' => $u['no'], 'kind' => strtolower($u['kind']), 'name' => $u['name'],
                'sub' => implode(' · ', array_filter([$u['kind'] === 'PALLET' ? $u['pallet_no'] : null, Locale::decimal($u['qty'], 2).' '.$u['uom'], $u['arrived'] ? t('Arrived').' '.Locale::d($u['arrived']) : null])),
                'tone' => $deadKeys->has($u['key']) || $u['old'] || $u['expiry_state'] === 'EXPIRED' ? 'danger' : null, 'up' => $u['no'] < $u['of'], 'down' => $u['no'] > 1,
                'tags' => array_values(array_filter([
                    ['label' => t(PalletService::KINDS[$u['kind']]), 'tone' => $u['kind'] === 'PALLET' ? 'info' : null],
                    $u['old'] ? ['label' => t('Old remnant').' '.$u['age_days'].' '.t('days'), 'tone' => 'danger'] : ($u['remnant'] ? ['label' => t('Remnant'), 'tone' => 'violet'] : null),
                    $deadKeys->has($u['key']) && ! $u['old'] ? ['label' => t('Dead stock'), 'tone' => 'danger'] : null,
                    $u['buried'] ? ['label' => t('Buried').' · '.$u['buried'].' '.t('above'), 'tone' => 'orange'] : null,
                    $u['expiry_state'] === 'EXPIRED' ? ['label' => t('Expired').' '.Locale::d($u['expiry']), 'tone' => 'danger'] : null,
                    $u['expiry_state'] === 'SOON' ? ['label' => t('Expires').' '.Locale::d($u['expiry']), 'tone' => 'warn'] : null,
                ]))], $s['stack']),
            'code' => $b->location_code, 'title' => trim(($z ? $z->zone_name.' · ' : '').t('Floor').' '.($z->floor_no ?? 1)),
            'level' => $z?->storage_mode === 'RACK' ? t('Level').' '.$b->stack_code : null, 'status' => $s['status'], 'dead' => $s['dead'],
            'capacity' => self::capacityText($s['used_kg'], $s['capacity_kg']), 'pct' => $s['pct'], 'caps' => $caps, 'capacityLabel' => self::capacityLabel($b),
            'expiry' => (bool) $s['expiry_state'],
            'note' => $notes ? implode(' ', $notes) : null, 'qr' => self::trackUrl('LOC', $b->location_code),
            'items' => array_map(fn (array $i) => ['code' => $i['code'], 'name' => $i['name'], 'qty' => Locale::decimal($i['qty'], 2).' '.$i['uom'],
                'idle' => self::idleText($i['idle_days']), 'dead' => $i['dead'], 'tags' => array_values(array_filter([
                    $i['arrived'] ? ['label' => t('Arrived').' '.Locale::d($i['arrived']), 'tone' => null] : null,
                    $i['expiry_state'] === 'EXPIRED' ? ['label' => t('Expired').' '.Locale::d($i['expiry']), 'tone' => 'danger'] : null,
                    $i['expiry_state'] === 'SOON' ? ['label' => t('Expires').' '.Locale::d($i['expiry']), 'tone' => 'warn'] : null,
                    $i['expiry_state'] === null && $i['expiry'] ? ['label' => t('Expiry').' '.Locale::d($i['expiry']), 'tone' => null] : null,
                    $i['pallet_count'] ? ['label' => $i['pallet_count'].' '.t($i['pallet_count'] === 1 ? 'unit' : 'units'), 'tone' => null] : null,
                ]))], $s['items']),
        ];
    }

    // "3 units · 2 pallets / max 3": semua satuan berlabel di bin itu, dan berapa di antaranya palet.
    public static function stackText(array $s): string
    {
        return implode(' · ', array_filter([
            $s['unit_count'].' '.t($s['unit_count'] === 1 ? 'unit' : 'units'),
            $s['pallet_count'] ? $s['pallet_count'].' '.t($s['pallet_count'] === 1 ? 'pallet' : 'pallets').($s['max_stack'] ? ' / '.t('Max').' '.$s['max_stack'] : '') : null,
            $s['pack_count'] && $s['pallet_count'] ? $s['pack_count'].' '.t($s['pack_count'] === 1 ? 'pack' : 'packs') : null,
        ]));
    }

    public static function capacityText(float $used, ?float $capacity): ?string
    {
        return $capacity ? Locale::number($used).' / '.Locale::number($capacity).' kg' : ($used > 0 ? Locale::number($used).' kg' : null);
    }

    public static function volumeText(float $used, ?float $capacity): ?string
    {
        return $capacity ? Locale::decimal($used, 2).' / '.Locale::decimal($capacity, 2).' m³' : ($used > 0 ? Locale::decimal($used, 2).' m³' : null);
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
            $room = $s['capacity_m3'] ? ' · '.str_replace(':free', Locale::decimal(max(0, $s['capacity_m3'] - $s['used_m3']), 2), t('free :free m³')) : '';

            return [$b->location_id => $b->location_code.' · '.$free.$room.($s['status'] === 'EMPTY' ? ' · '.t('empty') : '')];
        })->all();
    }

    // Isi objek form khusus x-mp::inv.warehouse-desk (layar Warehouse Map): pemilih gedung + pintasan, peta (x-dl::wh-map),
    // kapasitas per lantai, ringkasan, dan pembuat bin. 'wh' null = belum ada gedung; 'map' null = gedung belum punya ukuran.
    public static function desk(string $home): array
    {
        $wh = self::current(request()->integer('wh') ?: null);
        $screen = fn (string $slug) => route('v2.screen', $slug);
        if (! $wh) {
            return ['wh' => null, 'buildingsUrl' => $screen('wh-buildings')];
        }
        $map = $wh->hasLayout() ? self::map($wh) : null;
        $url = fn (string $name) => str_replace('987654321', '{id}', route($name, ['id' => 987654321]));
        $zones = $wh->zones()->where('activate', true)->get();
        $title = $wh->warehouse_code.' · '.$wh->warehouse_name;

        return [
            'wh' => $wh->id_warehouse, 'home' => $home, 'title' => $title, 'options' => self::options(), 'buildingsUrl' => $screen('wh-buildings'), 'zonesUrl' => $screen('wh-zones'),
            'links' => [['fa-building', 'Buildings', $screen('wh-buildings')], ['fa-vector-square', 'Storage Areas', $screen('wh-zones')],
                ['fa-table-cells', 'Bins', $screen('wh-bins')], ['fa-layer-group', 'Stack Monitor', $screen('stack-monitor')]],
            'generate' => ['show' => $zones->isNotEmpty(), 'action' => route('v2.wh.bins.generate'),
                'zones' => $zones->mapWithKeys(fn (WhZone $z) => [$z->id_zone => $z->zone_code.' · '.$z->zone_name.' · '.t('Floor').' '.$z->floor_no.' · '.$z->storage_mode])->all()],
            'map' => $map ? [
                'width' => $map['width'], 'height' => $map['height'], 'outline' => $map['outline'], 'floors' => $map['floors'], 'bins' => $map['bins'],
                'floor' => request()->integer('floor') ?: null, 'selected' => request()->integer('bin') ?: null, 'saveUrl' => route('v2.wh.zone-move'),
                'restackUrl' => str_replace(['987654321', '/restack/up'], ['{id}', '/restack/{dir}'], route('v2.wh.pallet.restack', ['id' => 987654321, 'dir' => 'up'])),
                'actions' => [
                    ['label' => t('Transfer'), 'icon' => 'fa-right-left', 'tone' => 'primary', 'when' => 'stock', 'post' => true, 'url' => $url('v2.wh.bin.transfer')],
                    ['label' => t('Label'), 'icon' => 'fa-qrcode', 'url' => $url('v2.wh.bin.label')],
                    ['label' => t('Block'), 'icon' => 'fa-ban', 'tone' => 'danger', 'when' => 'usable', 'post' => true, 'reason' => true, 'confirm' => t('Block this bin? It can no longer receive stock until it is unblocked.'), 'url' => $url('v2.wh.bin.block')],
                    ['label' => t('Unblock'), 'icon' => 'fa-circle-check', 'when' => 'blocked', 'post' => true, 'url' => $url('v2.wh.bin.unblock')],
                ],
                'capacity' => $map['capacity'], 'missingHeight' => collect($map['capacity'])->contains(fn (array $f) => $f['height'] === null),
                'summary' => str_replace([':area', ':floors', ':bins', ':used', ':cap', ':months'],
                    [Locale::number($map['totals']['area']), (string) $wh->floors(), Locale::number($map['totals']['bins']), Locale::number($map['totals']['used_kg']), Locale::number($map['totals']['capacity_kg']), (string) WarehouseStock::deadMonths()],
                    t('Floor area :area m2 on :floors floor(s), :bins bins, :used of :cap kg in use. Red = dead stock: the item has not moved for more than :months months (moving it to another bin does not count).')),
                'unassigned' => $map['unassigned'] ? str_replace(':n', (string) $map['unassigned'], t(':n bins of this building have no storage area yet, so they are not on the map. Set their area in Bins.')) : null,
            ] : null,
        ];
    }
}
