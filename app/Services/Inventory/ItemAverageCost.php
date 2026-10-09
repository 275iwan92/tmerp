<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Sys\SysConstant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
// Inkremental: bila pemanggil memberi baris awal ($from), rantai dihitung mulai baris itu saja. Keadaan sebelumnya dibaca dari kartu
// (nilai bin = value_eb baris terakhir tiap bin, qty = jumlah aliran, Avg = avg baris sebelumnya); semua angka rantai dibulatkan ke presisi
// kolom (nilai 4 desimal, Avg 5 desimal) supaya keadaan yang dibaca ulang SAMA PERSIS dengan hitung ulang penuh.
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
    public static function snapshot(string $class, int $itemId, ?int $partId, ?Model $from = null): array
    {
        $rows = self::rows($class, $itemId, $partId);
        if ($from) {
            $rows->whereRaw(self::stampSql($class).' >= ?', [self::stamp($class, $from)]);
        }

        return $rows->pluck('avg_unitprice', self::pk($class))->map(fn ($v) => $v === null ? null : (float) $v)->all();
    }

    /**
     * Hitung ulang nilai satu item. $before = hasil snapshot() (kosong = tidak melapor baris keluar yang berubah harga).
     *
     * @param  array<int, ?float>|null  $before
     * @return array{avg: ?float, qty: float, value: float, rows: int, revalued_rows: int, revalued_value: float}
     */
    public static function recalculate(string $class, int $itemId, ?int $partId, ?array $before = null, bool $write = true, ?Model $from = null): array
    {
        $open = DB::connection('tm2inv')->table(self::OPEN_TABLE)->where(['stock_class' => $class, 'item_id' => $itemId, 'part_id' => (int) $partId])->first();
        $stamp = $open ? Carbon::parse($open->cutover_date)->format('Y-m-d H:i:s') : null;
        $qty = $open ? (float) $open->open_qty : 0.0;
        $value = $open ? (float) $open->open_value : 0.0;
        $avg = $qty > self::EPS ? $value / $qty : ($open ? (float) $open->open_avg : null);
        $pk = self::pk($class);
        $binValue = [];
        $query = self::rows($class, $itemId, $partId);
        // shortcut: dokumen bertanggal sebelum / pada titik peralihan atau tanpa baris sebelumnya = hitung penuh (rantai pendek).
        if ($from && ($state = self::stateBefore($class, $itemId, $partId, $open, $from))) {
            [$qty, $value, $avg, $binValue] = $state;
            $query->whereRaw(self::stampSql($class).' >= ?', [self::stamp($class, $from)]);
        }
        $res = ['avg' => $avg, 'qty' => $qty, 'value' => $value, 'rows' => 0, 'revalued_rows' => 0, 'revalued_value' => 0.0];

        foreach ($query->lockForUpdate()->get() as $row) {
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
                $valueIn = $in > 0 ? round($in * (float) $avg, 4) : $valueIn;
                $binValue[$loc] = round($binValue[$loc] + round(($in - $out) * (float) $avg, 4), 4);
            } else {
                if ($in > 0) {
                    $price = (float) ($valueIn ?? 0);
                    $qty = round($qty + $in, 4);
                    $value = round($value + $price, 4);
                    $binValue[$loc] = round($binValue[$loc] + $price, 4);
                    $avg = $qty > self::EPS ? round($value / $qty, 5) : $avg;
                }
                $delta = $adj - $out;
                if ($delta != 0.0) {
                    $moved = round($delta * (float) $avg, 4);
                    $qty = round($qty + $delta, 4);
                    if (abs($qty) < self::EPS) {
                        $moved = -$value;
                        $qty = 0.0;
                    }
                    $value = round($value + $moved, 4);
                    $binValue[$loc] = round($binValue[$loc] + $moved, 4);
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

    /**
     * Keadaan rantai tepat sebelum baris $from, dibaca dari kartu. null = harus hitung penuh (tanpa baris sebelumnya, atau
     * $from bertanggal sebelum / pada titik peralihan).
     *
     * @return array{0: float, 1: float, 2: ?float, 3: array<int, float>}|null [qty, nilai, Avg, nilai per bin]
     */
    private static function stateBefore(string $class, int $itemId, ?int $partId, ?object $open, Model $from): ?array
    {
        $sx = self::stampSql($class);
        $at = self::stamp($class, $from);
        $cut = $open ? self::stamp($class, null, $open->cutover_date, (int) $open->cutover_doc_id) : null;
        if ($cut !== null && $at <= $cut) {
            return null;
        }
        $perBin = self::rows($class, $itemId, $partId)->reorder()->whereRaw("{$sx} < ?", [$at])->selectRaw("location_id, MAX({$sx}) AS mx")->groupBy('location_id')->pluck('mx', 'location_id');
        if ($perBin->isEmpty()) {
            return null;
        }
        $last = self::rows($class, $itemId, $partId)->reorder()->whereRaw("{$sx} IN (".implode(',', array_fill(0, $perBin->count(), '?')).')', $perBin->values()->all())->get();
        $binValue = $last->mapWithKeys(fn ($r) => [(int) $r->location_id => (float) $r->value_eb])->all();
        $prev = $last->sortBy(fn ($r) => self::stamp($class, $r))->last();

        $flow = self::rows($class, $itemId, $partId)->reorder()->whereRaw("{$sx} < ?", [$at])
            ->whereRaw("IFNULL(owner_type, 'COMPANY') = 'COMPANY'")->whereRaw("IFNULL(doc_type, '') <> 'TF'");
        if ($cut !== null) {
            $flow->whereRaw("{$sx} > ?", [$cut]);
        }
        $qty = round(($open ? (float) $open->open_qty : 0.0) + (float) $flow->selectRaw('COALESCE(SUM(qty_in - qty_out + qty_adj), 0) AS s')->value('s'), 4);
        $value = round(array_sum($binValue), 4);
        $avg = $cut === null || self::stamp($class, $prev) > $cut ? ($prev->avg_unitprice === null ? null : (float) $prev->avg_unitprice)
            : ($qty > self::EPS ? round($value / $qty, 5) : ($open->open_avg === null ? null : (float) $open->open_avg));

        return [$qty, $value, $avg, $binValue];
    }

    // Kunci urut sama dengan view v_*_onhand: tanggal-jam lalu nomor kartu, sebagai teks yang bisa dibandingkan.
    private static function stampSql(string $class): string
    {
        return "CONCAT(DATE_FORMAT(doc_date, '%Y%m%d%H%i%s'), LPAD(".self::pk($class).", 12, '0'))";
    }

    private static function stamp(string $class, ?Model $row, ?string $date = null, ?int $id = null): string
    {
        return Carbon::parse($row ? $row->doc_date : $date)->format('YmdHis').str_pad((string) ($row ? $row->getKey() : $id), 12, '0', STR_PAD_LEFT);
    }

    /**
     * Avg item SEKARANG untuk banyak item satu kelas (pembaca laporan / layar): Avg baris terakhir item (lintas bin, hanya milik Company),
     * atau Avg titik peralihan bila belum ada baris sesudahnya. Kunci hasil "item:part" (part 0 = tanpa part).
     *
     * @param  list<int>  $itemIds
     * @return array<string, ?float>
     */
    public static function currentAvgMany(string $class, array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        if (! $itemIds) {
            return [];
        }
        [$card, $itemKey] = StockCardChain::CARDS[$class];
        $pk = self::pk($class);
        $table = (new $card)->getTable();
        $sx = fn (string $a) => "CONCAT(DATE_FORMAT({$a}.doc_date, '%Y%m%d%H%i%s'), LPAD({$a}.{$pk}, 12, '0'))";
        $part = fn (string $a) => $class === 'FG' ? "IFNULL({$a}.parts_id, 0)" : '0';
        $last = $card::query()->from($table.' as x')->whereIn('x.'.$itemKey, $itemIds)->whereRaw("IFNULL(x.owner_type, 'COMPANY') = 'COMPANY'")
            ->groupByRaw('x.'.$itemKey.($class === 'FG' ? ', '.$part('x') : ''))->selectRaw("x.{$itemKey} AS k, MAX({$sx('x')}) AS mx");
        $rows = $card::query()->from($table.' as sc')->joinSub($last, 'l', fn ($j) => $j->on('sc.'.$itemKey, '=', 'l.k')->whereRaw($sx('sc').' = l.mx'))
            ->selectRaw("sc.{$itemKey} AS item_id, {$part('sc')} AS part_id, sc.doc_date, sc.{$pk} AS row_pk, sc.avg_unitprice")->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->item_id.':'.$r->part_id] = ['stamp' => Carbon::parse($r->doc_date)->format('YmdHis').str_pad((string) $r->row_pk, 12, '0', STR_PAD_LEFT),
                'avg' => $r->avg_unitprice === null ? null : (float) $r->avg_unitprice];
        }
        foreach (DB::connection('tm2inv')->table(self::OPEN_TABLE)->where('stock_class', $class)->whereIn('item_id', $itemIds)->get() as $o) {
            $key = $o->item_id.':'.$o->part_id;
            if (($out[$key]['stamp'] ?? '') > self::stamp($class, null, $o->cutover_date, (int) $o->cutover_doc_id)) {
                continue;
            }
            $out[$key] = ['avg' => (float) $o->open_qty > self::EPS ? (float) $o->open_value / (float) $o->open_qty : ($o->open_avg === null ? null : (float) $o->open_avg)];
        }

        return array_map(fn ($v) => $v['avg'], $out);
    }

    public static function currentAvg(string $class, int $itemId, ?int $partId): ?float
    {
        return self::currentAvgMany($class, [$itemId])[$itemId.':'.(int) $partId] ?? null;
    }

    // Harga rata-rata yang dibaca layar / laporan: Avg item bila scope = ITEM, selain itu harga bin yang diberikan pemanggil (perilaku lama).
    public static function readerAvg(string $class, int $itemId, ?int $partId, ?float $binAvg): ?float
    {
        return self::enabled() ? (self::currentAvg($class, $itemId, $partId) ?? $binAvg) : $binAvg;
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
