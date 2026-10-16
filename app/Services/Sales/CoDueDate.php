<?php

/*
 * REMARK
 * Tujuan       : Due date baris Customer Order otomatis = tanggal dasar + CO_LEAD_WORKDAYS hari kerja (bawaan 14), batas maksimum pengiriman (Jo 2026-10-16, A.10); dasar = ACC Digital (Proof Digital) terbaru produk, atau tanggal CO untuk CO direct.
 * Sumber hulu  : tm2mst.prod_product_acc (acc_type DIGITAL, acc_at), co_order.co_date (CO direct), WorkCalendar (hari kerja + hari besar), konstanta CO_LEAD_WORKDAYS (Settings > Constants).
 * Input        : baris CO (produk, mode CO, tanggal CO); saat CO Confirm, seluruh baris CO.
 * Output       : CarbonImmutable tanggal (null bila produk belum punya ACC Digital); refresh() menghitung ulang baris yang masih otomatis.
 * Dipakai oleh : CoOrderItem (event saving), CoOrderBuilder::submit.
 * Status       : Aktif. Sales boleh menimpa (due_source MANUAL); sesudah Confirm baris beku.
 */

namespace App\Services\Sales;

use App\Models\Master\ProdProductAcc;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sys\SysConstant;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;

final class CoDueDate
{
    private static function acc(int $productId): ?ProdProductAcc
    {
        return ProdProductAcc::query()->where('product_id', $productId)->where('acc_type', 'DIGITAL')->orderByDesc('acc_at')->first();
    }

    // CO direct (INSTANT, repeat order tanpa proof baru) = tanggal CO; CO dari Quotation = ACC Digital terbaru produk. Null bila dasarnya belum ada.
    public static function suggest(CoOrderItem $item, ?WorkCalendar $calendar = null): ?CarbonImmutable
    {
        $base = $item->order?->isInstant() ? $item->order->co_date : self::acc((int) $item->product_id)?->acc_at;

        return $base ? ($calendar ?? new WorkCalendar)->addWorkdays(CarbonImmutable::parse($base), self::days()) : null;
    }

    private static function days(): int
    {
        return (int) SysConstant::number('CO_LEAD_WORKDAYS', 14);
    }

    // Sebelum Confirm: baris yang masih otomatis dihitung ulang (ACC baru boleh masuk sampai saat itu); yang ditimpa Sales tidak disentuh.
    public static function refresh(CoOrder $order): void
    {
        foreach ($order->items->where('line_kind', 'PRODUCT')->where('due_source', 'ACC') as $item) {
            $item->due_date = null; // memicu hitung ulang di CoOrderItem::saving
            $item->save();
        }
    }
}
