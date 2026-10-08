<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

// Satu sumber Available untuk barang jadi (FG), sama seperti v_rm_available untuk bahan baku (B-14 rencana / scope bagian 6 aturan 6):
// Available = stok FG yang bisa dipakai (v_fg_onhand, bin tidak diblokir) dikurangi stok FG yang sudah dipegang booking Confirmed / Held
// (rm_booking_alloc source_type FG, pada booking yang tidak batal dan belum selesai). Dipakai MaterialPlanningEngine (netting FG) dan
// layar mana pun yang menampilkan Available FG; jangan menghitung ulang di tempat lain.
final class FgAvailability
{
    /**
     * @param  list<int>|null  $productIds  null = semua produk yang punya stok atau booking
     * @return array<int, array{onhand: float, booked: float, available: float}> per product_id
     */
    public static function byProduct(?array $productIds = null): array
    {
        $inv = DB::connection('tm2inv');
        $onhand = $inv->table('v_fg_onhand')->where('is_usable', 1)->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds ?: [0]))
            ->selectRaw('product_id, SUM(qty_onhand) as qty')->groupBy('product_id')->pluck('qty', 'product_id')->map(fn ($q) => (float) $q)->all();
        $booked = $inv->table('rm_booking_alloc as a')->join('rm_booking_d as d', 'd.booking_detail_id', '=', 'a.booking_detail_id')->join('rm_booking as h', 'h.booking_id', '=', 'd.booking_id')
            ->where('a.source_type', 'FG')->where('h.stat_confirm', 1)->where('h.stat_cancel', 0)->where('h.stat_closed', 0)->when($productIds !== null, fn ($q) => $q->whereIn('d.product_id', $productIds ?: [0]))
            ->selectRaw('d.product_id as pid, SUM(a.qty) as qty')->groupBy('d.product_id')->pluck('qty', 'pid')->map(fn ($q) => (float) $q)->all();

        $out = [];
        foreach (array_unique(array_merge(array_keys($onhand), array_keys($booked))) as $id) {
            $out[(int) $id] = ['onhand' => $onhand[$id] ?? 0.0, 'booked' => $booked[$id] ?? 0.0, 'available' => ($onhand[$id] ?? 0.0) - ($booked[$id] ?? 0.0)];
        }

        return $out;
    }

    public static function available(int $productId): float
    {
        return self::byProduct([$productId])[$productId]['available'] ?? 0.0;
    }
}
