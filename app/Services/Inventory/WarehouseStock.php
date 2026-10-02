<?php

namespace App\Services\Inventory;

use App\Models\Inventory\StockOnhand;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\WhLoc;
use App\Models\Sys\SysConstant;
use Illuminate\Support\Collection;

// Sisi BACA stok per bin untuk peta gudang, transfer, label, dan lacak (2026-10-02). Sumber = view saldo stok yang sama
// dengan laporan (v_rm / v_sfg / v_fg_onhand); tidak ada angka yang dihitung di layar.
//   posisi stok = satu item (RM / SFG / FG) di satu bin dengan saldo > 0; kunci "KELAS:item:part:bin" (part 0 = tanpa part).
//   dead stock  = pergerakan terakhir ITEM itu (di lokasi mana pun, tanpa menghitung transfer) lebih lama dari
//                 ASSISTANT_DEADSTOCK_MONTHS bulan, atau tidak pernah bergerak -- definisi yang sama dengan jawaban asisten
//                 dan widget Deadstock Value, supaya angka di semua tempat sama.
//   berat       = perkiraan: satuan KG -> qty; lembaran ber-gramatur + ukuran -> qty x gsm x p x l; selain itu tidak diketahui.
final class WarehouseStock
{
    public const CLASSES = ['RM' => 'Raw Material', 'SFG' => 'Semi Finished Goods', 'FG' => 'Finished Goods'];

    public const BIN_TONES = ['EMPTY' => 'neutral', 'IN_USE' => 'info', 'FULL' => 'warn', 'OVER' => 'tangerine', 'BLOCKED' => 'neutral', 'DEAD' => 'danger'];

    // Bin dianggap penuh mulai persen ini dari kapasitas beratnya.
    private const FULL_PCT = 90;

    public static function deadMonths(): int
    {
        return max(1, (int) SysConstant::number('ASSISTANT_DEADSTOCK_MONTHS', 6));
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
            foreach ($q->get() as $r) {
                $itemId = (int) $r->getAttribute($itemKey);
                $partId = $class === 'FG' ? ((int) $r->parts_id ?: null) : null;
                $moved = self::date($r->last_item_movement);
                $rows->push([
                    'key' => self::key($class, $itemId, $partId, (int) $r->location_id), 'class' => $class, 'item_id' => $itemId, 'part_id' => $partId,
                    'location_id' => (int) $r->location_id, 'location_code' => $r->location_code, 'qty' => (float) $r->qty_onhand, 'uom' => trim((string) $r->uom_code),
                    'value' => $r->value_onhand === null ? null : (float) $r->value_onhand, 'avg' => $r->avg_unitprice === null ? null : (float) $r->avg_unitprice,
                    'last_movement' => $moved, 'idle_days' => $moved ? (int) now()->startOfDay()->diffInDays($moved, true) : null,
                    'dead' => $moved === null || $moved < $cutoff,
                ]);
            }
        }

        return self::withItems($rows);
    }

    // Kode, nama, dan perkiraan berat tiap posisi (lintas-DB tm2inv -> tm2mst: satu query master per kelas stok).
    private static function withItems(Collection $rows): Collection
    {
        $ids = fn (string $class) => $rows->where('class', $class)->pluck('item_id')->unique()->values()->all();
        $rm = MatRaw::withoutGlobalScopes()->whereKey($ids('RM'))->get(['material_id', 'material_code', 'material_name', 'category1', 'gram_weight', 'cm_length', 'cm_width'])->keyBy('material_id');
        $sfg = ProdPart::withoutGlobalScopes()->whereKey($ids('SFG'))->get(['id_part', 'part_code', 'part_name'])->keyBy('id_part');
        $fg = ProdProduct::withoutGlobalScopes()->whereKey($ids('FG'))->get(['id_product', 'product_code', 'product_name'])->keyBy('id_product');

        return $rows->map(function (array $r) use ($rm, $sfg, $fg) {
            [$code, $name, $group] = match ($r['class']) {
                'RM' => [$rm[$r['item_id']]->material_code ?? null, $rm[$r['item_id']]->material_name ?? null, $rm[$r['item_id']]->category1 ?? null],
                'SFG' => [$sfg[$r['item_id']]->part_code ?? null, $sfg[$r['item_id']]->part_name ?? null, null],
                default => [$fg[$r['item_id']]->product_code ?? null, $fg[$r['item_id']]->product_name ?? null, null],
            };

            return $r + ['code' => $code ?: '#'.$r['item_id'], 'name' => $name ?: '(item not found in master)', 'group' => $group,
                'weight_kg' => self::weightKg($r['qty'], $r['uom'], $r['class'] === 'RM' ? ($rm[$r['item_id']] ?? null) : null, (string) $name)];
        })->values();
    }

    // Perkiraan berat satu posisi (kg); null = tidak bisa diperkirakan (kapasitas bin itu hanya dihitung dari yang diketahui).
    public static function weightKg(float $qty, string $uom, ?MatRaw $material, string $name): ?float
    {
        if (in_array(strtoupper($uom), ['KG', 'KGS', 'KILOGRAM'], true)) {
            return round($qty, 2);
        }
        $gsm = (float) ($material?->gram_weight ?? 0);
        $l = (float) ($material?->cm_length ?? 0);
        $w = (float) ($material?->cm_width ?? 0);
        // Banyak kertas lama menulis gramatur + ukuran hanya di namanya: "DUPLEX 350 GR 84.5 X 72 CM", "IVORY 300 79 X 109".
        if (($gsm <= 0 || $l <= 0 || $w <= 0) && $material?->category1 === 'Paper'
            && preg_match('/\b(\d{2,3})\s*(?:GR|GSM)?\b[^0-9]{1,24}?(\d{2,3}(?:[.,]\d+)?)\s*X\s*(\d{2,3}(?:[.,]\d+)?)/i', $name, $m)) {
            [$gsm, $l, $w] = [(float) $m[1], (float) str_replace(',', '.', $m[2]), (float) str_replace(',', '.', $m[3])];
        }
        // RIM = 500 lembar.
        $sheets = strtoupper($uom) === 'RIM' ? $qty * 500 : $qty;

        return $gsm > 0 && $l > 0 && $w > 0 ? round($sheets * $gsm * $l * $w / 10000000, 2) : null;
    }

    // Ringkasan tiap bin: isi, berat terpakai, persen kapasitas, status, dan apakah memuat dead stock.
    /** @param  Collection<int, WhLoc>  $bins
     *  @return array<int, array<string, mixed>> kunci = location_id */
    public static function binSummaries(Collection $bins): array
    {
        $positions = self::positions($bins->pluck('location_id')->all())->groupBy('location_id');
        $out = [];
        foreach ($bins as $bin) {
            $items = $positions->get($bin->location_id, collect())->sortBy('code')->values();
            $known = $items->whereNotNull('weight_kg');
            $used = round((float) $known->sum('weight_kg'), 2);
            $capacity = $bin->capacity_kg === null ? null : (float) $bin->capacity_kg;
            $pct = $capacity > 0 ? round($used / $capacity * 100, 1) : null;
            $dead = $items->contains('dead', true);
            $out[$bin->location_id] = [
                'items' => $items->all(), 'used_kg' => $used, 'capacity_kg' => $capacity, 'pct' => $pct, 'unknown_weight' => $items->count() - $known->count(),
                'dead' => $dead,
                'status' => match (true) {
                    ! $bin->is_usable => 'BLOCKED',
                    $items->isEmpty() => 'EMPTY',
                    $pct !== null && $pct > 100 => 'OVER',
                    $pct !== null && $pct >= self::FULL_PCT => 'FULL',
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
