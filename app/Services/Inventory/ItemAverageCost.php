<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Sys\SysConstant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Valuasi "Average per item" (D13 / D15), hanya aktif bila sys_constant INV_AVG_SCOPE = ITEM (BIN = perilaku lama, kelas ini tidak dipanggil).
// Satu rantai nilai per item (FG: product + part) lintas SEMUA bin, urut (doc_date, doc_id). Qty per bin tetap diurus StockCardChain.
//   penerimaan   : Avg baru = (nilai item + value_in) / (qty item + masuk)
//   keluar / adj : bernilai qty x Avg item saat itu, Avg tidak berubah; qty item 0 memaksa nilai item 0 (sisa pembulatan ikut baris itu)
//   transfer     : memindah qty x Avg item antar bin; total item dan Avg tidak berubah, tanpa jurnal
//   owner <> COMPANY : tidak masuk rantai (nilai bin tetap)
// Nilai kartu: avg_unitprice = Avg item sesudah baris; value_eb = nilai BIN sesudah baris (nilai bin hanya bergerak sebesar nilai alirannya),
// jadi jumlah value_eb terakhir semua bin = nilai item dan StockLedger / view v_*_onhand yang membaca delta value_eb tetap benar.
// Titik peralihan (inv:revalue-per-item --apply) disimpan di tm2inv.inv_item_avg_open: baris sampai titik itu tidak dihitung ulang,
// rantai mulai dari qty / nilai total saat itu. Tanpa titik peralihan rantai mulai dari nol (item baru).
final class ItemAverageCost
{
    private const EPS = 0.00005;

    public const OPEN_TABLE = 'inv_item_avg_open';

    public static function enabled(): bool
    {
        return strtoupper(trim((string) SysConstant::text('INV_AVG_SCOPE', 'BIN'))) === 'ITEM';
    }

    // Avg per kartu sebelum StockCardChain menulis ulang, supaya perubahan harga baris keluar bisa dilaporkan.
    /** @return array<int, ?float> */
    public static function snapshot(string $class, int $itemId, ?int $partId): array
    {
        return self::rows($class, $itemId, $partId)->pluck('avg_unitprice', self::pk($class))->map(fn ($v) => $v === null ? null : (float) $v)->all();
    }

    /**
     * Hitung ulang nilai satu item. $before = hasil snapshot() (kosong = tidak melapor baris keluar yang berubah harga).
     *
     * @param  array<int, ?float>|null  $before
     * @return array{avg: ?float, qty: float, value: float, rows: int, revalued_rows: int, revalued_value: float}
     */
    public static function recalculate(string $class, int $itemId, ?int $partId, ?array $before = null, bool $write = true): array
    {
        $open = DB::connection('tm2inv')->table(self::OPEN_TABLE)->where(['stock_class' => $class, 'item_id' => $itemId, 'part_id' => (int) $partId])->first();
        $stamp = $open ? Carbon::parse($open->cutover_date)->format('Y-m-d H:i:s') : null;
        $qty = $open ? (float) $open->open_qty : 0.0;
        $value = $open ? (float) $open->open_value : 0.0;
        $avg = $qty > self::EPS ? $value / $qty : ($open ? (float) $open->open_avg : null);
        $pk = self::pk($class);
        $binValue = [];
        $res = ['avg' => $avg, 'qty' => $qty, 'value' => $value, 'rows' => 0, 'revalued_rows' => 0, 'revalued_value' => 0.0];

        foreach (self::rows($class, $itemId, $partId)->lockForUpdate()->get() as $row) {
            $loc = (int) $row->location_id;
            $date = Carbon::parse($row->doc_date)->format('Y-m-d H:i:s');
            if ($open && ($date < $stamp || ($date === $stamp && (int) $row->getKey() <= (int) $open->cutover_doc_id))) {
                $binValue[$loc] = (float) $row->value_eb;

                continue;
            }

            $in = (float) $row->qty_in;
            $out = (float) $row->qty_out;
            $adj = (float) $row->qty_adj;
            $valueIn = $row->value_in === null ? null : (float) $row->value_in;
            $binValue[$loc] ??= 0.0;
            $oldAvg = $row->avg_unitprice === null ? null : (float) $row->avg_unitprice;

            if (($row->owner_type ?: 'COMPANY') !== 'COMPANY') {
                $valueIn = null;
            } elseif ($row->doc_type === 'TF') {
                $valueIn = $in > 0 ? $in * (float) $avg : $valueIn;
                $binValue[$loc] += ($in - $out) * (float) $avg;
            } else {
                if ($in > 0) {
                    $price = (float) ($valueIn ?? 0);
                    $qty += $in;
                    $value += $price;
                    $binValue[$loc] += $price;
                    $avg = $qty > self::EPS ? $value / $qty : $avg;
                }
                $delta = $adj - $out;
                if ($delta != 0.0) {
                    $moved = $delta * (float) $avg;
                    $qty += $delta;
                    if (abs($qty) < self::EPS) {
                        $moved = -$value;
                        $qty = 0.0;
                    }
                    $value += $moved;
                    $binValue[$loc] += $moved;
                    if ($out > 0 && $before !== null && array_key_exists((int) $row->getKey(), $before) && ! self::same($before[(int) $row->getKey()], $avg)) {
                        $res['revalued_rows']++;
                        $res['revalued_value'] += $out * ((float) $avg - (float) $before[(int) $row->getKey()]);
                    }
                }
            }

            $fill = ['avg_unitprice' => $avg, 'value_eb' => $binValue[$loc], 'value_in' => $valueIn];
            if ($write && (! self::same($oldAvg, $avg) || abs((float) $row->value_eb - $binValue[$loc]) > 0.000005 || ! self::same($row->value_in === null ? null : (float) $row->value_in, $valueIn))) {
                $row->forceFill($fill)->save();
                $res['rows']++;
            }
        }

        return ['avg' => $avg, 'qty' => $qty, 'value' => $value] + $res;
    }

    // Semua baris kartu satu item (FG: satu product + part), urut waktu.
    public static function rows(string $class, int $itemId, ?int $partId): Builder
    {
        [$card, $itemKey] = StockCardChain::CARDS[$class];
        $q = $card::query()->where($itemKey, $itemId)->orderBy('doc_date')->orderBy(self::pk($class));
        if ($class === 'FG') {
            $partId ? $q->where('parts_id', $partId) : $q->whereNull('parts_id');
        }

        return $q;
    }

    private static function pk(string $class): string
    {
        return (new (StockCardChain::CARDS[$class][0]))->getKeyName();
    }

    private static function same(?float $a, ?float $b): bool
    {
        return $a === null || $b === null ? $a === $b : abs($a - $b) < 0.000005;
    }
}
