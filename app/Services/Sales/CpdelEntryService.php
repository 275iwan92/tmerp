<?php

/*
 * REMARK
 * Tujuan       : Aturan Split / Pindah tanggal dan Gabung jadwal kirim CPDEL (dipindah apa adanya dari komponen v1 pages::co.delivery-plan). Murni penjadwalan: tidak menyentuh stok / COGS. Split tanpa qty = hanya pindah tanggal; split dengan qty = pecah jadi baris PLANNED baru, total qty tetap. Gabung hanya baris PLANNED dari CO + produk yang sama, disatukan ke baris bertanggal paling awal.
 * Sumber hulu  : CoOrderDelivery.
 * Input        : id baris jadwal kirim + qty split + tanggal baru, atau daftar id baris yang digabung.
 * Output       : co_order_delivery; nilai kembali = pesan error ('' = OK).
 * Dipakai oleh : App\Support\V2\Data\Cpdel (split / merge), tests/Feature/CpdelEntryTest.
 * Status       : Aktif (T10 tahap 7c, 2026-10-22).
 */

namespace App\Services\Sales;

use App\Models\Sdi\CoOrderDelivery;
use Illuminate\Support\Facades\Auth;

final class CpdelEntryService
{
    public static function confirmSplit(int $deliveryId, string $splitQty, string $splitDate): string
    {
        $delivery = CoOrderDelivery::findOrFail($deliveryId);

        if ($delivery->status !== 'PLANNED') {
            return 'Baris ini sudah SHIPPED, tidak bisa diubah.';
        }

        $qty = trim($splitQty) === '' ? null : (float) $splitQty;

        if ($qty === null) {
            $delivery->update(['ship_date' => $splitDate, 'updated_by' => Auth::user()->login]);
        } else {
            if ($qty <= 0 || $qty >= (float) $delivery->ship_qty) {
                return "Qty split harus lebih dari 0 dan kurang dari qty baris ini ({$delivery->ship_qty}).";
            }

            $login = Auth::user()->login;
            CoOrderDelivery::create([
                'order_id' => $delivery->order_id,
                'item_id' => $delivery->item_id,
                'shipment_type' => 'PARTIAL',
                'ship_date' => $splitDate,
                'ship_qty' => $qty,
                'status' => 'PLANNED',
                'split_from_id' => $delivery->id_delivery,
                'created_by' => $login,
                'updated_by' => $login,
            ]);
            $delivery->update([
                'ship_qty' => (float) $delivery->ship_qty - $qty,
                'shipment_type' => 'PARTIAL',
                'updated_by' => $login,
            ]);
        }

        return '';
    }

    public static function confirmMerge(array $selectedIds): string
    {
        if (count($selectedIds) < 2) {
            return 'Pilih minimal 2 baris utk digabung.';
        }

        $rows = CoOrderDelivery::whereIn('id_delivery', $selectedIds)->get();

        if ($rows->pluck('order_id')->unique()->count() > 1 || $rows->pluck('item_id')->unique()->count() > 1) {
            return 'Baris yang digabung harus dari CO & produk yang sama.';
        }
        if ($rows->contains(fn ($r) => $r->status !== 'PLANNED')) {
            return 'Hanya baris berstatus PLANNED yang bisa digabung.';
        }

        $winner = $rows->sortBy('ship_date')->first();
        $totalQty = $rows->sum('ship_qty');

        $winner->update(['ship_qty' => $totalQty, 'updated_by' => Auth::user()->login]);
        CoOrderDelivery::whereIn('id_delivery', $rows->pluck('id_delivery')->diff([$winner->id_delivery]))->delete();

        return '';
    }
}
