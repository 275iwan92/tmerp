<?php

namespace App\Services\Inventory;

use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\SfgStockcard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

// Rantai saldo kartu stok SATU POSISI (item di satu bin; FG: product + part), urut (doc_date, doc_id). Aturan user
// 2026-10-02: dokumen yang tanggalnya lebih awal dari baris terakhir DISISIPKAN di tempatnya, lalu semua baris sesudahnya
// dihitung ulang (saldo awal, saldo akhir, harga rata-rata bergerak, nilai) -- saldo = baris terakhir tetap benar.
//   harga masuk  = value_in / qty_in baris itu (ditulis saat posting). Baris lama tanpa value_in: diturunkan dari angka
//                  yang tersimpan sebelum dihitung ulang.
//   transfer     = baris keluar transfer membawa harga rata-rata bin asal ke bin tujuan. Bila harga itu berubah, baris
//                  masuk pasangannya diberi nilai baru dan rantai bin tujuan ikut dihitung ulang (merambat; selalu maju
//                  di urutan waktu, jadi pasti berhenti).
//   saldo minus  = dilaporkan ke pemanggil (baris pertama yang jadi minus karena hitung ulang ini); pemanggil yang
//                  memutuskan menolak. Baris yang sudah minus sebelumnya dan tidak bertambah minus tidak dilaporkan.
// Hanya dipanggil di dalam transaksi tm2inv oleh penulis kartu (StockPostingService); layar tidak memanggilnya.
final class StockCardChain
{
    // kelas => [model kartu, kolom item]
    public const CARDS = ['RM' => [RmStockcard::class, 'material_id'], 'SFG' => [SfgStockcard::class, 'parts_id'], 'FG' => [FgStockcard::class, 'product_id']];

    private const QTY_EPS = 0.00005;

    private const PRICE_EPS = 0.000005;

    // Semua baris satu posisi.
    public static function position(string $class, int $itemId, ?int $partId, int $locationId): Builder
    {
        [$card, $itemKey] = self::CARDS[$class];
        $q = $card::query()->where($itemKey, $itemId)->where('location_id', $locationId);
        if ($class === 'FG') {
            $partId ? $q->where('parts_id', $partId) : $q->whereNull('parts_id');
        }

        return $q;
    }

    /**
     * Hitung ulang rantai posisi $from mulai dari baris itu (ikut dihitung) sampai baris terakhir.
     * $fresh = baris $from baru saja disisipkan (angka saldonya belum ada).
     *
     * @return array{rows: int, negative: ?array{doc_id: int, doc_date: string, qty: float}, revalued_rows: int, revalued_value: float}
     */
    public static function recalculate(string $class, Model $from, bool $fresh = false): array
    {
        [, $itemKey] = self::CARDS[$class];
        $position = fn () => self::position($class, (int) $from->{$itemKey}, $class === 'FG' ? ($from->parts_id ? (int) $from->parts_id : null) : null, (int) $from->location_id);
        $at = Carbon::parse($from->doc_date)->format('Y-m-d H:i:s');
        $id = (int) $from->getKey();

        $prev = $position()->where(fn ($q) => $q->where('doc_date', '<', $at)->orWhere(fn ($q2) => $q2->where('doc_date', $at)->where('doc_id', '<', $id)))
            ->orderByDesc('doc_date')->orderByDesc('doc_id')->lockForUpdate()->first();
        $rows = $position()->where(fn ($q) => $q->where('doc_date', '>', $at)->orWhere(fn ($q2) => $q2->where('doc_date', $at)->where('doc_id', '>=', $id)))
            ->orderBy('doc_date')->orderBy('doc_id')->lockForUpdate()->get();

        $out = ['rows' => 0, 'negative' => null, 'revalued_rows' => 0, 'revalued_value' => 0.0];
        $balance = (float) ($prev?->qty_eb ?? 0);
        $avg = self::price($prev?->avg_unitprice);
        $oldPrevAvg = $avg;
        $prevId = $prev?->getKey();
        $cascade = [];

        foreach ($rows as $row) {
            $isNew = $fresh && (int) $row->getKey() === $id;
            $in = (float) $row->qty_in;
            $outQty = (float) $row->qty_out;
            $oldBb = (float) $row->qty_bb;
            $oldEb = (float) $row->qty_eb;
            $oldAvg = self::price($row->avg_unitprice);

            $newAvg = $avg;
            if ($in > 0) {
                $price = match (true) {
                    $row->value_in !== null => (float) $row->value_in / $in,
                    $oldAvg === null => null,
                    default => (($oldBb + $in) * $oldAvg - $oldBb * (float) $oldPrevAvg) / $in,
                };
                $newAvg = $avg === null && $price === null ? null : (($balance * (float) $avg) + ($in * (float) $price)) / max($balance + $in, 0.0001);
            }
            $newEb = $balance + $in - $outQty + (float) $row->qty_adj;

            if ($out['negative'] === null && $newEb < -self::QTY_EPS && ($isNew || $newEb < $oldEb - self::QTY_EPS)) {
                $out['negative'] = ['doc_id' => (int) $row->getKey(), 'doc_date' => Carbon::parse($row->doc_date)->toDateString(), 'qty' => $newEb];
            }

            $repriced = ! $isNew && ! self::samePrice($oldAvg, $newAvg);
            if ($repriced && $outQty > 0) {
                if ($row->doc_type === 'TF') {
                    $cascade[] = [$row, $newAvg];
                } else {
                    $out['revalued_rows']++;
                    $out['revalued_value'] += $outQty * ((float) $newAvg - (float) $oldAvg);
                }
            }

            $newValue = $newAvg === null ? null : $newEb * $newAvg;
            if ($isNew || abs($oldBb - $balance) > self::QTY_EPS || abs($oldEb - $newEb) > self::QTY_EPS || ! self::samePrice($oldAvg, $newAvg)
                || ! self::samePrice(self::price($row->value_eb), $newValue) || (int) $row->previous_doc_id !== (int) $prevId) {
                $row->forceFill(['previous_doc_id' => $prevId, 'qty_bb' => $balance, 'qty_eb' => $newEb, 'avg_unitprice' => $newAvg, 'value_eb' => $newValue])->save();
                $out['rows']++;
            }

            $oldPrevAvg = $oldAvg;
            $balance = $newEb;
            $avg = $newAvg;
            $prevId = $row->getKey();
        }

        // Nilai yang ikut pindah berubah -> baris masuk di bin tujuan diberi nilai baru, rantainya dihitung ulang.
        foreach ($cascade as [$outRow, $price]) {
            $pair = $outRow->newQuery()->where('reference_detail_id', $outRow->reference_detail_id)
                ->where('reference_name', preg_replace('/OUT$/', 'IN', (string) $outRow->reference_name))->first();
            if (! $pair || (int) $pair->getKey() === (int) $outRow->getKey()) {
                continue;
            }
            $pair->forceFill(['value_in' => $price === null ? null : (float) $pair->qty_in * $price])->save();
            $more = self::recalculate($class, $pair);
            $out['rows'] += $more['rows'];
            $out['negative'] ??= $more['negative'];
            $out['revalued_rows'] += $more['revalued_rows'];
            $out['revalued_value'] += $more['revalued_value'];
        }

        return $out;
    }

    private static function price(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private static function samePrice(?float $a, ?float $b): bool
    {
        return $a === null || $b === null ? $a === $b : abs($a - $b) < self::PRICE_EPS;
    }
}
