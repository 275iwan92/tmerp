<?php

namespace App\Services\Sales;

use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\QuoQuotation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// "1 CO = 1 Quotation" (keputusan user 2026-08-20) -- item CO adalah snapshot dari
// quo_quotation_item saat CO pertama kali dibuat (bukan live-linked; harga/qty CO
// boleh menyimpang dari Quotation sesudahnya kalau memang perlu, sesuai prinsip
// snapshot CLAUDE.md §4).
class CoOrderBuilder
{
    public static function findOrCreateFromQuotation(QuoQuotation $quotation): ?CoOrder
    {
        if ($quotation->doc_status !== 'PUBLISHED') {
            return null;
        }

        $existing = CoOrder::where('quotation_id', $quotation->id_quotation)->first();

        if ($existing) {
            return $existing;
        }

        $quotation->loadMissing('items.inquiry');
        $login = Auth::user()->login;

        return DB::connection('tm2sdi')->transaction(function () use ($quotation, $login) {
            $primaryInquiry = $quotation->items->firstWhere('source_type', 'INQUIRY_TIER')?->inquiry;

            $order = CoOrder::create([
                'co_no' => CoOrder::nextCoNo(),
                'co_date' => now()->toDateString(),
                'quotation_id' => $quotation->id_quotation,
                'discount_doc_pct' => $primaryInquiry?->discount_doc_value,
                'discount_qty_pct' => $primaryInquiry?->discount_qty_value,
                'doc_status' => 'DRAFT',
                'created_by' => $login,
                'updated_by' => $login,
            ]);

            $seq = 1;

            foreach ($quotation->items as $qi) {
                $item = CoOrderItem::create([
                    'order_id' => $order->id_order,
                    'line_seq' => $seq++,
                    'quotation_item_id' => $qi->id_item,
                    'line_kind' => $qi->source_type === 'FREEFORM' ? 'SERVICE' : 'PRODUCT',
                    'inquiry_id' => $qi->inquiry_id,
                    'description' => $qi->description,
                    'spec_note' => $qi->spec_note,
                    'qty' => $qi->qty,
                    'qty_unit' => $qi->qty_unit,
                    'unit_price' => $qi->unit_price,
                    'discount_pct' => $qi->discount_pct,
                    'created_by' => $login,
                    'updated_by' => $login,
                ]);
                $item->recalcSubtotal();
                $item->save();
            }

            // co_id "terakhir menyerap" -- convenience lookup di iqp_inquiry, lihat
            // migration 2026_08_20_000008 & IqpInquiry::lastCo().
            $quotation->items->pluck('inquiry_id')->filter()->unique()->each(
                fn ($id) => \App\Models\Sdi\IqpInquiry::where('id_inquiry', $id)->update(['co_id' => $order->id_order])
            );

            return $order;
        });
    }
}
