<?php

namespace App\Services\Inventory;

use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\SfgStockcard;
use App\Models\Inventory\StockOnhand;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Models\Sys\SysConstant;
use Illuminate\Support\Collection;

// Sisi BACA stok per bin untuk peta gudang, transfer, label, dan lacak (2026-10-02). Sumber = view saldo stok yang sama
// dengan laporan (v_rm / v_sfg / v_fg_onhand); tidak ada angka yang dihitung di layar.
//   posisi stok = satu item (RM / SFG / FG) di satu bin dengan saldo > 0; kunci "KELAS:item:part:bin" (part 0 = tanpa part).
//   dead stock  = pergerakan terakhir ITEM itu (di lokasi mana pun, tanpa menghitung transfer) lebih lama dari
//                 ASSISTANT_DEADSTOCK_MONTHS bulan, atau tidak pernah bergerak -- definisi yang sama dengan jawaban asisten
//                 dan widget Deadstock Value, supaya angka di semua tempat sama.
//   datang      = tanggal barang masuk ke bin itu (baris kartu IN / saldo awal terakhir); bin yang hanya menerima lewat
//                 transfer memakai tanggal masuk terakhir item itu di mana pun.
//   kapasitas   = (user 2026-10-02) LUAS dan TINGGI: tapak bin (m2, dari luas area dibagi jumlah posisi) x tinggi tumpuk
//                 (tinggi area, atau tinggi lantai gedung; rak dibagi jumlah tingkat) = m3. Kg tetap ada (capacity_kg).
//   berat / isi = perkiraan: satuan KG -> qty; lembaran ber-gramatur + ukuran -> berat dan volume; selain itu tidak
//                 diketahui dan tidak dihitung (disebut di layar).
final class WarehouseStock
{
    public const CLASSES = ['RM' => 'Raw Material', 'SFG' => 'Semi Finished Goods', 'FG' => 'Finished Goods'];

    private const CARDS = ['RM' => [RmStockcard::class, 'material_id'], 'SFG' => [SfgStockcard::class, 'parts_id'], 'FG' => [FgStockcard::class, 'product_id']];

    public static function deadMonths(): int
    {
        return max(1, (int) SysConstant::number('ASSISTANT_DEADSTOCK_MONTHS', 6));
    }

    // Bin dianggap penuh mulai persen ini dari kapasitasnya (berat atau volume, yang lebih tinggi).
    public static function fullPct(): float
    {
        return max(1.0, SysConstant::number('WH_BIN_FULL_PCT', 90));
    }

    public static function expirySoonDays(): int
    {
        return max(0, (int) SysConstant::number('WH_EXPIRY_SOON_DAYS', 30));
    }

    public static function key(string $class, int $itemId, ?int $partId, int $locationId): string
    {
        return $class.':'.$itemId.':'.(int) $partId.':'.$locationId;
    }

    /** @return array{class: string, item_id: int, part_id: ?int, location_id: int}|null */
    public static function parseKey(string $key): ?array
    {
        if (! preg_match('/^(RM|SFG|FG):(\d+):(\d+):(\d+)$/', $key, $m)) {
            return null;
        }

        return ['class' => $m[1], 'item_id' => (int) $m[2], 'part_id' => (int) $m[3] ?: null, 'location_id' => (int) $m[4]];
    }

    // Posisi stok bersaldo > 0. $locationIds null = semua bin; $itemFilter = ['RM' => [id..], ...] untuk lacak satu item.
    /** @return Collection<int, array<string, mixed>> */
    public static function positions(?array $locationIds = null, ?array $itemFilter = null): Collection
    {
        $cutoff = now()->subMonths(self::deadMonths())->toDateString();
        $rows = collect();
        foreach (array_keys(self::CLASSES) as $class) {
            if ($itemFilter !== null && empty($itemFilter[$class])) {
                continue;
            }
            $itemKey = StockOnhand::ITEM_KEYS[$class];
            $q = StockOnhand::of($class)->where('qty_onhand', '>', 0);
            if ($locationIds !== null) {
                $q->whereIn('location_id', $locationIds ?: [0]);
            }
            if ($itemFilter !== null) {
                $q->whereIn($itemKey, $itemFilter[$class]);
            }
            $found = $q->get();
            $arrived = self::arrivals($class, $found->pluck($itemKey)->unique()->all());
            foreach ($found as $r) {
                $itemId = (int) $r->getAttribute($itemKey);
                $partId = $class === 'FG' ? ((int) $r->parts_id ?: null) : null;
                $moved = self::date($r->last_item_movement);
                $rows->push([
                    'key' => self::key($class, $itemId, $partId, (int) $r->location_id), 'class' => $class, 'item_id' => $itemId, 'part_id' => $partId,
                    'location_id' => (int) $r->location_id, 'location_code' => $r->location_code, 'qty' => (float) $r->qty_onhand, 'uom' => trim((string) $r->uom_code),
                    'value' => $r->value_onhand === null ? null : (float) $r->value_onhand, 'avg' => $r->avg_unitprice === null ? null : (float) $r->avg_unitprice,
                    'last_movement' => $moved, 'idle_days' => $moved ? (int) now()->startOfDay()->diffInDays($moved, true) : null,
                    'dead' => $moved === null || $moved < $cutoff,
                    'arrived' => $arrived['bin'][$itemId.':'.$r->location_id] ?? $arrived['item'][$itemId] ?? null,
                ]);
            }
        }

        return self::withItems($rows);
    }

    // Tanggal masuk terakhir (baris kartu IN / saldo awal BB) per item per bin, dan per item di mana pun.
    /** @return array{bin: array<string, string>, item: array<int, string>} */
    private static function arrivals(string $class, array $itemIds): array
    {
        [$card, $itemKey] = self::CARDS[$class];
        $out = ['bin' => [], 'item' => []];
        if (! $itemIds) {
            return $out;
        }
        $rows = $card::query()->whereIn($itemKey, $itemIds)->whereIn('doc_type', ['IN', 'BB'])
            ->selectRaw("{$itemKey} AS item, location_id, MAX(doc_date) AS d")->groupBy($itemKey, 'location_id')->get();
        foreach ($rows as $r) {
            $d = self::date($r->d);
            if ($d === null) {
                continue;
            }
            $out['bin'][$r->item.':'.$r->location_id] = $d;
            $out['item'][(int) $r->item] = max($out['item'][(int) $r->item] ?? '', $d);
        }

        return $out;
    }

    // Kode, nama, perkiraan berat dan volume tiap posisi (lintas-DB tm2inv -> tm2mst: satu query master per kelas stok).
    private static function withItems(Collection $rows): Collection
    {
        $ids = fn (string $class) => $rows->where('class', $class)->pluck('item_id')->unique()->values()->all();
        $rm = MatRaw::withoutGlobalScopes()->whereKey($ids('RM'))->get(['material_id', 'material_code', 'material_name', 'category1', 'gram_weight', 'cm_length', 'cm_width', 'mm_thickness', 'shelf_life_days'])->keyBy('material_id');
        $sfg = ProdPart::withoutGlobalScopes()->whereKey($ids('SFG'))->get(['id_part', 'part_code', 'part_name'])->keyBy('id_part');
        $fg = ProdProduct::withoutGlobalScopes()->whereKey($ids('FG'))->get(['id_product', 'product_code', 'product_name'])->keyBy('id_product');

        return $rows->map(function (array $r) use ($rm, $sfg, $fg) {
            [$code, $name, $group] = match ($r['class']) {
                'RM' => [$rm[$r['item_id']]->material_code ?? null, $rm[$r['item_id']]->material_name ?? null, $rm[$r['item_id']]->category1 ?? null],
                'SFG' => [$sfg[$r['item_id']]->part_code ?? null, $sfg[$r['item_id']]->part_name ?? null, null],
                default => [$fg[$r['item_id']]->product_code ?? null, $fg[$r['item_id']]->product_name ?? null, null],
            };
            $material = $r['class'] === 'RM' ? ($rm[$r['item_id']] ?? null) : null;

            return $r + ['code' => $code ?: '#'.$r['item_id'], 'name' => $name ?: '(item not found in master)', 'group' => $group,
                'shelf_life_days' => (int) ($material?->shelf_life_days ?? 0) ?: null,
                'weight_kg' => self::weightKg($r['qty'], $r['uom'], $material, (string) $name),
                'volume_m3' => self::volumeM3($r['qty'], $r['uom'], $material, (string) $name)];
        })->values();
    }

    // Gramatur (g/m2) dan ukuran lembar (cm) satu bahan: kolom master, atau dibaca dari nama kertas
    // ("DUPLEX 350 GR 84.5 X 72 CM", "IVORY 300 79 X 109"). null = bukan lembaran yang dikenali.
    /** @return array{0: float, 1: float, 2: float}|null [gsm, panjang cm, lebar cm] */
    private static function sheet(?MatRaw $material, string $name): ?array
    {
        $gsm = (float) ($material?->gram_weight ?? 0);
        $l = (float) ($material?->cm_length ?? 0);
        $w = (float) ($material?->cm_width ?? 0);
        if (($gsm <= 0 || $l <= 0 || $w <= 0) && $material?->category1 === 'Paper'
            && preg_match('/\b(\d{2,3})\s*(?:GR|GSM)?\b[^0-9]{1,24}?(\d{2,3}(?:[.,]\d+)?)\s*X\s*(\d{2,3}(?:[.,]\d+)?)/i', $name, $m)) {
            [$gsm, $l, $w] = [(float) $m[1], (float) str_replace(',', '.', $m[2]), (float) str_replace(',', '.', $m[3])];
        }

        return $gsm > 0 && $l > 0 && $w > 0 ? [$gsm, $l, $w] : null;
    }

    // RIM = 500 lembar.
    private static function sheets(float $qty, string $uom): float
    {
        return strtoupper($uom) === 'RIM' ? $qty * 500 : $qty;
    }

    // Perkiraan berat satu posisi (kg); null = tidak bisa diperkirakan (kapasitas bin itu hanya dihitung dari yang diketahui).
    public static function weightKg(float $qty, string $uom, ?MatRaw $material, string $name): ?float
    {
        if (in_array(strtoupper($uom), ['KG', 'KGS', 'KILOGRAM'], true)) {
            return round($qty, 2);
        }
        $s = self::sheet($material, $name);

        return $s ? round(self::sheets($qty, $uom) * $s[0] * $s[1] * $s[2] / 10000000, 2) : null;
    }

    // Perkiraan volume satu posisi (m3) untuk lembaran: jumlah lembar x luas lembar x tebal. Tebal = mm_thickness master,
    // atau gramatur x WH_PAPER_BULK_CM3_PER_G (mikron). null = tidak bisa diperkirakan.
    public static function volumeM3(float $qty, string $uom, ?MatRaw $material, string $name): ?float
    {
        $s = self::sheet($material, $name);
        if (! $s) {
            return null;
        }
        $thicknessMm = (float) ($material?->mm_thickness ?? 0) ?: $s[0] * max(0.5, SysConstant::number('WH_PAPER_BULK_CM3_PER_G', 1.3)) / 1000;

        return round(self::sheets($qty, $uom) * ($s[1] / 100) * ($s[2] / 100) * ($thicknessMm / 1000), 3);
    }

    // Ukuran tiap bin dari denah: tapak (m2) = luas area dibagi jumlah posisi di area itu; tinggi = tinggi tumpuk area
    // (atau tinggi lantai gedung) dibagi jumlah tingkat di posisi itu; volume = tapak x tinggi (null bila tinggi belum diisi).
    /** @param  Collection<int, WhLoc>  $bins
     *  @return array<int, array{area_m2: ?float, height_m: ?float, volume_m3: ?float}> kunci = location_id */
    public static function binGeometry(Collection $bins): array
    {
        $zones = WhZone::query()->whereKey($bins->pluck('zone_id')->filter()->unique()->all())->get()->keyBy('id_zone');
        $buildings = WhWarehouse::query()->whereKey($zones->pluck('warehouse_id')->unique()->all())->get()->keyBy('id_warehouse');
        // Semua bin aktif area-area itu (bukan hanya yang diminta) supaya jumlah posisi dan tingkat benar.
        $cells = WhLoc::query()->whereIn('zone_id', $zones->keys()->all() ?: [0])->where('activate', true)->get(['location_id', 'zone_id', 'row_code', 'col_code'])
            ->groupBy('zone_id')->map(fn ($g) => $g->groupBy(fn ($b) => $b->row_code.'|'.$b->col_code)->map->count());
        $out = [];
        foreach ($bins as $b) {
            $zone = $zones->get($b->zone_id);
            if (! $zone) {
                $out[$b->location_id] = ['area_m2' => null, 'height_m' => null, 'volume_m3' => null];

                continue;
            }
            $zoneCells = $cells->get($zone->id_zone, collect());
            $positions = $zone->storage_mode === 'AREA' ? 1 : max(1, $zoneCells->count());
            $levels = $zone->storage_mode === 'AREA' ? 1 : max(1, (int) ($zoneCells[$b->row_code.'|'.$b->col_code] ?? 1));
            $area = round($zone->areaM2() / $positions, 2);
            $height = $zone->stack_height_m ?: $buildings->get($zone->warehouse_id)?->floorHeight($zone->floor_no);
            $binHeight = $height ? round($height / $levels, 2) : null;
            $out[$b->location_id] = ['area_m2' => $area, 'height_m' => $binHeight, 'volume_m3' => $binHeight ? round($area * $binHeight, 2) : null];
        }

        return $out;
    }

    // Ringkasan tiap bin: isi, berat dan volume terpakai terhadap kapasitas, status, dead stock, palet, kedaluwarsa.
    /** @param  Collection<int, WhLoc>  $bins
     *  @return array<int, array<string, mixed>> kunci = location_id */
    public static function binSummaries(Collection $bins): array
    {
        $ids = $bins->pluck('location_id')->all();
        $positions = self::positions($ids)->groupBy('location_id');
        $geometry = self::binGeometry($bins);
        $pallets = InvPallet::query()->where('status', 'ACTIVE')->whereIn('location_id', $ids ?: [0])->get()->groupBy('location_id');
        $soon = self::expirySoonDays();
        $full = self::fullPct();
        $out = [];
        foreach ($bins as $bin) {
            $binPallets = $pallets->get($bin->location_id, collect());
            $items = $positions->get($bin->location_id, collect())->sortBy('code')->values()->map(function (array $i) use ($binPallets, $soon) {
                $mine = $binPallets->filter(fn (InvPallet $p) => $p->positionKey() === $i['key']);
                $expiry = $mine->pluck('expiry_date')->filter()->min();
                $states = $mine->map(fn (InvPallet $p) => $p->expiryState($soon))->filter();

                return $i + ['pallet_count' => $mine->count(), 'pallet_qty' => (float) $mine->sum('qty'), 'expiry' => $expiry?->toDateString(),
                    'expiry_state' => $states->contains('EXPIRED') ? 'EXPIRED' : ($states->contains('SOON') ? 'SOON' : null)];
            });
            $g = $geometry[$bin->location_id];
            $usedKg = round((float) $items->whereNotNull('weight_kg')->sum('weight_kg'), 2);
            $usedM3 = round((float) $items->whereNotNull('volume_m3')->sum('volume_m3'), 3);
            $capKg = $bin->capacity_kg === null ? null : (float) $bin->capacity_kg;
            $pctKg = $capKg > 0 ? round($usedKg / $capKg * 100, 1) : null;
            $pctM3 = $g['volume_m3'] > 0 ? round($usedM3 / $g['volume_m3'] * 100, 1) : null;
            $pct = max($pctKg ?? -1, $pctM3 ?? -1);
            $pct = $pct < 0 ? null : $pct;
            $out[$bin->location_id] = [
                'items' => $items->all(), 'used_kg' => $usedKg, 'capacity_kg' => $capKg, 'pct_kg' => $pctKg,
                'used_m3' => $usedM3, 'capacity_m3' => $g['volume_m3'], 'pct_m3' => $pctM3, 'area_m2' => $g['area_m2'], 'height_m' => $g['height_m'],
                'pct' => $pct, 'unknown_weight' => $items->whereNull('weight_kg')->count(), 'unknown_volume' => $items->whereNull('volume_m3')->count(),
                'dead' => $items->contains('dead', true), 'pallet_count' => $binPallets->count(),
                'expiry_state' => $items->contains('expiry_state', 'EXPIRED') ? 'EXPIRED' : ($items->contains('expiry_state', 'SOON') ? 'SOON' : null),
                'status' => match (true) {
                    ! $bin->is_usable => 'BLOCKED',
                    $items->isEmpty() => 'EMPTY',
                    $pct !== null && $pct > 100 => 'OVER',
                    $pct !== null && $pct >= $full => 'FULL',
                    default => 'IN_USE',
                },
            ];
        }

        return $out;
    }

    // View lama bisa memuat tanggal kosong (0000-00-00) untuk saldo warisan: dianggap belum pernah bergerak.
    private static function date(mixed $value): ?string
    {
        $date = substr((string) $value, 0, 10);

        return $date === '' || str_starts_with($date, '0000') ? null : $date;
    }
}
