<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

// Batch dari data lama (v2.01): tiap lot_no yang sudah ada di kartu stok dan palet menjadi satu batch (kelas + item + lot),
// berstatus Released (stok lama sudah dipakai). Lot dinormalkan (huruf besar, spasi tepi dibuang); lot kosong = batch
// bernama konstanta INV_NO_LOT_BATCH_CODE. Kedaluwarsa batch = tanggal terdekat dari paletnya. Sesudah itu batch_id diisi di
// kartu stok, baris dokumen, dan palet yang masih kosong. Idempoten: batch yang sudah ada dipakai lagi, baris yang sudah
// bertaut tidak disentuh. $write = false (--check) hanya menghitung, tidak menulis apa pun.
final class BatchBackfillService
{
    // kelas => [tabel kartu, tabel baris dokumen, kolom item]
    private const TABLES = ['RM' => ['rm_stockcard', 'rm_stock_d', 'material_id'], 'SFG' => ['sfg_stockcard', 'sfg_stock_d', 'parts_id'], 'FG' => ['fg_stockcard', 'fg_stock_d', 'product_id']];

    /**
     * @param  list<string>|null  $classes  null = RM, SFG, FG
     * @param  list<int>|null  $itemIds  hanya item ini (uji dan perbaikan terarah); null = semua
     * @return array{batches_created: int, batches_existing: int, groups_merged: int, linked: array{cards: int, lines: int, pallets: int}, by_class: array<string, array<string, int>>}
     */
    public static function run(bool $write, string $login = 'inv:backfill-batch', ?array $classes = null, ?array $itemIds = null): array
    {
        $out = ['batches_created' => 0, 'batches_existing' => 0, 'groups_merged' => 0, 'linked' => ['cards' => 0, 'lines' => 0, 'pallets' => 0], 'by_class' => []];
        foreach (array_intersect(array_keys(self::TABLES), $classes ?? array_keys(self::TABLES)) as $class) {
            $groups = self::groups($class, $itemIds);
            $meta = StockItemMeta::load($class, array_column($groups, 'item_id'));
            $mine = ['groups' => count($groups), 'created' => 0, 'existing' => 0, 'merged' => 0, 'cards' => 0, 'lines' => 0, 'pallets' => 0];
            foreach ($groups as $g) {
                $batch = BatchService::findByLot($class, $g['item_id'], $g['lot']);
                if ($batch) {
                    $mine['existing']++;
                } else {
                    $mine['created']++;
                    if ($write) {
                        $m = $meta[$g['item_id']] ?? null;
                        $batch = BatchService::create($class, $g['item_id'], $g['lot'], ['item_code' => $m['code'] ?? null, 'item_name' => $m['name'] ?? null,
                            'expiry_date' => $g['expiry'], 'qc_status' => 'RELEASED', 'notes' => 'Created from existing lot data'], $login);
                    }
                }
                $mine['merged'] += count($g['variants']) > 1 ? 1 : 0;
                foreach (self::targets($class, $g) as $kind => $query) {
                    $mine[$kind] += $write ? (int) $query->update(['batch_id' => $batch->id_batch]) : $query->count();
                }
            }
            $out['by_class'][$class] = $mine;
            $out['batches_created'] += $mine['created'];
            $out['batches_existing'] += $mine['existing'];
            $out['groups_merged'] += $mine['merged'];
            foreach (['cards', 'lines', 'pallets'] as $k) {
                $out['linked'][$k] += $mine[$k];
            }
        }

        return $out;
    }

    /** @return list<array{item_id: int, lot: ?string, norm: string, expiry: ?string, variants: list<string>}> kelompok lot per item dari kartu stok dan palet */
    private static function groups(string $class, ?array $itemIds): array
    {
        [$card, , $itemCol] = self::TABLES[$class];
        $inv = DB::connection('tm2inv');
        $found = [];
        $add = function (int $item, ?string $raw, ?string $expiry) use (&$found) {
            $norm = BatchService::normalizeLot($raw);
            $g = &$found[$item.'|'.$norm];
            $g ??= ['item_id' => $item, 'lot' => trim((string) $raw) === '' ? null : trim((string) $raw), 'norm' => $norm, 'expiry' => null, 'variants' => []];
            $g['variants'][trim((string) $raw)] = trim((string) $raw);
            if ($expiry && ($g['expiry'] === null || $expiry < $g['expiry'])) {
                $g['expiry'] = $expiry;
            }
        };
        $cards = $inv->table($card)->whereNotNull($itemCol)->when($itemIds !== null, fn (Builder $q) => $q->whereIn($itemCol, $itemIds ?: [0]))->selectRaw("{$itemCol} AS item, lot_no")->distinct()->get();
        foreach ($cards as $r) {
            $add((int) $r->item, $r->lot_no, null);
        }
        $pallets = $inv->table('inv_pallet')->where('stock_class', $class)->when($itemIds !== null, fn (Builder $q) => $q->whereIn('item_id', $itemIds ?: [0]))->get(['item_id', 'lot_no', 'expiry_date']);
        foreach ($pallets as $p) {
            $add((int) $p->item_id, $p->lot_no, $p->expiry_date ? substr((string) $p->expiry_date, 0, 10) : null);
        }
        foreach ($found as &$g) {
            $g['variants'] = array_values($g['variants']);
        }

        return array_values($found);
    }

    /** @return array<string, Builder> baris yang belum bertaut untuk satu kelompok (kunci = nama hitungan di laporan) */
    private static function targets(string $class, array $g): array
    {
        [$card, $line, $itemCol] = self::TABLES[$class];
        $inv = DB::connection('tm2inv');
        // Kelompok "tanpa lot" memuat lot kosong dan lot yang memang bernama sama dengan kode batch tanpa lot.
        $noLot = $g['norm'] === BatchService::normalizeLot(null);
        $lot = fn (Builder $q) => $noLot ? $q->whereRaw("(TRIM(IFNULL(lot_no, '')) = '' OR UPPER(TRIM(lot_no)) = ?)", [$g['norm']]) : $q->whereRaw('UPPER(TRIM(lot_no)) = ?', [$g['norm']]);

        return [
            'cards' => $lot($inv->table($card)->where($itemCol, $g['item_id'])->whereNull('batch_id')),
            'lines' => $lot($inv->table($line)->where($itemCol, $g['item_id'])->whereNull('batch_id')),
            'pallets' => $lot($inv->table('inv_pallet')->where('stock_class', $class)->where('item_id', $g['item_id'])->whereNull('batch_id')),
        ];
    }
}
