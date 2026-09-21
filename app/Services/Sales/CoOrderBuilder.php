<?php

namespace App\Services\Sales;

use App\Models\Master\CustCustomer;
use App\Models\Master\ProdProduct;
use App\Models\Master\SalesPerson;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\IqpInquiry;
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
                'entry_mode' => 'INTEGRATED',
                'quotation_id' => $quotation->id_quotation,
                'customer_id' => $quotation->customer_id,
                'sales_person_id' => self::resolveSalesPerson($primaryInquiry?->pic_sales),
                'payment_term' => $quotation->payment_term,
                'currency_code' => $quotation->currency_code,
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
                    'product_id' => $qi->inquiry?->parent_product_id,
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
            // migration 2026_08_20_000008 & IqpInquiry::lastCo(). sub_state=>CONVERTED
            // (win-rate, docs/BACKLOG.md §3 "Win-rate Sales") -- kolom & value CONVERTED
            // TERNYATA sudah ada di skema (enum-teks sub_state) sejak awal, cuma tak
            // pernah ada 1 pun titik kode yang menuliskannya -- titik natural utk itu
            // adalah SAAT CO pertama kali lahir dari Quotation ini (deal resmi menang).
            $quotation->items->pluck('inquiry_id')->filter()->unique()->each(
                fn ($id) => IqpInquiry::where('id_inquiry', $id)->update(['co_id' => $order->id_order, 'sub_state' => 'CONVERTED'])
            );

            return $order;
        });
    }

    // MODE INSTANT (keputusan user 2026-09-21): CO lahir langsung dari grid tanpa
    // Quotation -- meniru alur manual ScriptCase V1 & kebiasaan order verbal yang
    // masih hidup. Bedanya cuma JALUR INPUT: dokumen, validasi lanjutan, dan
    // muara ke CPDEL persis sama dengan mode INTEGRATED.
    public static function createInstant(CustCustomer $customer, ?int $salesPersonId = null): CoOrder
    {
        $login = Auth::user()->login;

        return CoOrder::create([
            'co_no' => CoOrder::nextCoNo(),
            'co_date' => now()->toDateString(),
            'entry_mode' => 'INSTANT',
            'quotation_id' => null,
            'customer_id' => $customer->id_customer,
            'sales_person_id' => $salesPersonId,
            'payment_term' => $customer->payment_term,
            'currency_code' => $customer->currency_code ?: 'IDR',
            'ship_address_detail' => $customer->default_address,
            'doc_status' => 'DRAFT',
            'created_by' => $login,
            'updated_by' => $login,
        ]);
    }

    // Baris CO mode INSTANT SELALU berasal dari Master Product -- harga, satuan,
    // dan toleransi qty di-SNAPSHOT dari master saat baris dibuat (§4), jadi
    // perubahan master sesudahnya tidak menggeser CO yang sudah jalan.
    public static function addProductLine(CoOrder $order, ProdProduct $product, float $qty = 1): CoOrderItem
    {
        $login = Auth::user()->login;
        $nextSeq = ((int) $order->items()->max('line_seq')) + 1;

        $item = new CoOrderItem([
            'order_id' => $order->id_order,
            'line_seq' => $nextSeq,
            'line_kind' => 'PRODUCT',
            'product_id' => $product->id_product,
            'description' => trim($product->product_name.' ('.$product->product_code.')'),
            'spec_note' => $product->description,
            'qty' => $qty,
            'qty_unit' => $product->uom_sales ?: 'Pcs',
            'qty_min' => $product->qty_limit_min,
            'qty_max' => $product->qty_limit_max,
            'unit_price' => $product->default_selling_price ?? 0,
            'discount_pct' => 0,
            'created_by' => $login,
            'updated_by' => $login,
        ]);

        $item->order_id = $order->id_order;
        $item->recalcSubtotal();
        $item->save();

        return $item;
    }

    private static function resolveSalesPerson(?string $picSalesName): ?int
    {
        if (! $picSalesName) {
            return null;
        }

        // iqp_inquiry.pic_sales masih teks bebas (bukan FK) -- cocokkan by nama,
        // tidak cocok ya biarkan null daripada menebak.
        return SalesPerson::where('sales_name', trim($picSalesName))->value('id_sales_person');
    }
}
