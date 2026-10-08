<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvMovement;
use Illuminate\Support\Collection;

// Rumus bersambung Stock Card (user 2026-10-08): BB baris = saldo ITEM (semua lokasi) sebelum baris itu, EB = BB + In - Out + Adj,
// dan BB baris berikutnya = EB baris sebelumnya. Dihitung PER ITEM, jadi laporan yang memuat banyak item tetap bersambung per item.
// Baris yang tampil di layar adalah baris TERBARU kartu (urutan doc_date, doc_id yang sama dengan rantai); saldo sebelum baris
// tertua yang tampil = jumlah bersih seluruh riwayat item dikurangi jumlah bersih baris yang tampil. Transfer = Out di lokasi
// asal lalu In di lokasi tujuan (saldo item turun lalu naik kembali).
final class ItemBalanceChain
{
    /**
     * @param  Collection<int, InvMovement>  $shown  baris kartu yang tampil, satu kelas stok
     * @return array<int, array{bb: float, eb: float}> doc_id => saldo item sebelum / sesudah baris itu
     */
    public static function forNewest(string $class, Collection $shown): array
    {
        $byItem = $shown->groupBy('item_id');
        if ($byItem->isEmpty()) {
            return [];
        }
        $totals = InvMovement::query()->where('stock_class', $class)->whereIn('item_id', $byItem->keys()->all())->groupBy('item_id')
            ->selectRaw('item_id, SUM(IFNULL(qty_in, 0) - IFNULL(qty_out, 0) + IFNULL(qty_adj, 0)) AS net')->pluck('net', 'item_id');

        $out = [];
        foreach ($byItem as $itemId => $rows) {
            $ascending = $rows->sortBy([['doc_date', 'asc'], ['doc_id', 'asc']]);
            $running = round((float) ($totals[$itemId] ?? 0) - $ascending->sum(fn (InvMovement $r) => self::net($r)), 4);
            foreach ($ascending as $r) {
                $bb = $running;
                $running = round($bb + self::net($r), 4);
                $out[(int) $r->doc_id] = ['bb' => $bb, 'eb' => $running];
            }
        }

        return $out;
    }

    private static function net(InvMovement $r): float
    {
        return (float) $r->qty_in - (float) $r->qty_out + (float) $r->qty_adj;
    }
}
