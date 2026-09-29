<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrderDelivery;
use Livewire\Component;

// Customer Plan Delivery v2. Aturan Split/Pindah tanggal & Gabung = komponen v1 pages::co.delivery-plan yang SAMA
// (confirmSplit/confirmMerge). Hanya CO CONFIRMED; tidak menyentuh stok (itu Delivery Order).
final class Cpdel
{
    public const STATUS_TONES = ['PLANNED' => 'warn', 'SHIPPED' => 'success', 'TERLAMBAT' => 'danger'];

    public static function component(): Component
    {
        return app('livewire')->new('pages::co.delivery-plan');
    }

    public static function query()
    {
        return CoOrderDelivery::with(['order', 'item'])->whereHas('order', fn ($q) => $q->where('doc_status', 'CONFIRMED'));
    }

    public static function late(CoOrderDelivery $d): bool
    {
        if ($d->status !== 'PLANNED' || ! $d->item?->due_date) {
            return false;
        }

        return $d->item->due_date->isPast() || ($d->ship_date && $d->ship_date->gt($d->item->due_date));
    }

    public static function rows(): array
    {
        $rows = self::query()->orderBy('ship_date')->limit(1000)->get();
        $customers = CustCustomer::whereIn('id_customer', $rows->pluck('order.customer_id')->unique())->pluck('customer_name', 'id_customer');

        return $rows->map(fn (CoOrderDelivery $d) => [
            'id' => $d->id_delivery,
            'co' => $d->order?->co_no,
            'customer' => $customers[$d->order?->customer_id] ?? '—',
            'item' => $d->item?->description,
            'type' => $d->shipment_type,
            'ship_date' => $d->ship_date?->toDateString(),
            'qty' => (float) $d->ship_qty,
            'due' => $d->item?->due_date?->toDateString(),
            'status' => self::late($d) ? 'TERLAMBAT' : ($d->status ?? 'PLANNED'),
        ])->all();
    }

    // Baris lain CO + product yang sama (kandidat Gabung).
    public static function siblings(CoOrderDelivery $d)
    {
        return CoOrderDelivery::where('order_id', $d->order_id)->where('item_id', $d->item_id)->where('status', 'PLANNED')
            ->whereKeyNot($d->id_delivery)->orderBy('ship_date')->get();
    }

    // qty kosong = pindah tanggal saja; qty diisi = pecah jadi baris baru di tanggal itu. Kembalian: pesan error atau null.
    public static function split(int $id, ?string $qty, string $date): ?string
    {
        $c = self::component();
        $c->splitDeliveryId = $id;
        $c->splitQty = (string) $qty;
        $c->splitDate = $date;
        $c->confirmSplit();

        return $c->splitError ?: null;
    }

    public static function merge(array $ids): ?string
    {
        $c = self::component();
        $c->mergeSelectedIds = array_map('intval', $ids);
        $c->confirmMerge();

        return $c->mergeError ?: null;
    }
}
