<?php

/*
 * REMARK
 * Tujuan       : Aturan entri Customer Order (dipindah apa adanya dari komponen v1 pages::co.entry): header, wilayah kirim, baris PRODUCT/SERVICE, ganti Master Product, due date / due note / Qty Minim, jadwal kirim, biaya luar. Semua perubahan hanya saat DRAFT; baris snapshot Quotation tidak boleh dihapus dan harga/qty/deskripsinya tidak boleh diubah (hanya due date, due note, Qty Minim, dan tautan Master Product).
 * Sumber hulu  : CoOrder, CoOrderItem, CoOrderDelivery, CoOrderExternalCost, ProdProduct, CoOrderBuilder::addProductLine.
 * Input        : CoOrder + nilai field / id baris / id produk.
 * Output       : co_order, co_order_item, co_order_delivery, co_order_external_cost.
 * Dipakai oleh : App\Support\V2\Data\CustomerOrder (layar v2 customer-order), tests/Feature/CoEntryServiceTest.
 * Status       : Aktif (T10 pindahan v1, 2026-10-20).
 */

namespace App\Services\Sales;

use App\Models\Master\ProdProduct;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderExternalCost;
use App\Models\Sdi\CoOrderItem;
use Illuminate\Support\Facades\Auth;

final class CoEntryService
{
    public const HEADER_FIELDS = ['co_date', 'customer_po_no', 'ship_address_detail', 'discount_qty_pct', 'notes', 'sales_person_id', 'payment_term', 'is_ppn',
        'shipment_method', 'recipient_customer_id', 'shipper_customer_id', 'forwarder_customer_id'];

    public const ITEM_FIELDS = ['description', 'qty', 'qty_unit', 'unit_price', 'discount_pct'];

    public const DELIVERY_FIELDS = ['shipment_type', 'ship_date', 'ship_qty'];

    public const COST_FIELDS = ['category', 'description', 'nominal', 'tax_treatment'];

    public static function editable(CoOrder $o): bool
    {
        return $o->doc_status === 'DRAFT';
    }

    public static function updateHeader(CoOrder $o, string $field, mixed $value): void
    {
        if (! self::editable($o) || ! in_array($field, self::HEADER_FIELDS, true)) {
            return;
        }

        $o->update([$field => $value === '' ? null : $value, 'updated_by' => self::login()]);
    }

    public static function setShipArea(CoOrder $o, ?int $areaId, ?string $areaLevel, ?string $label, ?string $postalCode): void
    {
        if (! self::editable($o)) {
            return;
        }

        $o->update(['ship_area_id' => $areaId, 'ship_area_level' => $areaLevel, 'ship_address_label' => $label, 'ship_postal_code' => $postalCode, 'updated_by' => self::login()]);
    }

    // Baris PRODUK normalnya lewat pickProduct; addItem melayani baris JASA yang tidak punya master.
    public static function addItem(CoOrder $o, string $kind): void
    {
        if (! self::editable($o)) {
            return;
        }

        CoOrderItem::create([
            'order_id' => $o->id_order,
            'line_seq' => ((int) $o->items()->max('line_seq')) + 1,
            'line_kind' => $kind === 'SERVICE' ? 'SERVICE' : 'PRODUCT',
            'description' => '',
            'qty' => 1,
            'qty_unit' => 'Pcs',
            'unit_price' => 0,
            'discount_pct' => 0,
            'subtotal' => 0,
            'created_by' => self::login(),
            'updated_by' => self::login(),
        ]);
    }

    // $itemId null = tambah baris baru; terisi = ganti Master Product baris itu.
    public static function pickProduct(CoOrder $o, int $productId, ?int $itemId = null): void
    {
        if (! self::editable($o)) {
            return;
        }

        $product = ProdProduct::findOrFail($productId);

        if ($itemId === null) {
            CoOrderBuilder::addProductLine($o, $product);

            return;
        }

        $item = CoOrderItem::where('order_id', $o->id_order)->findOrFail($itemId);
        $item->product_id = $product->id_product;
        $item->qty_min = $product->qty_limit_min;
        $item->qty_max = $product->qty_limit_max;

        // Baris snapshot Quotation: cuma ditautkan ke master, deskripsi/harga hasil negosiasi tidak ditimpa.
        if ($item->quotation_item_id === null) {
            $item->description = trim($product->product_name.' ('.$product->product_code.')');
            $item->qty_unit = $product->uom_sales ?: $item->qty_unit;
            $item->unit_price = $product->default_selling_price ?? $item->unit_price;
            $item->recalcSubtotal();
        }

        $item->updated_by = self::login();
        $item->save();
    }

    // Baris hasil snapshot Quotation tidak boleh dihapus (jaga jejak); hanya baris manual (quotation_item_id null).
    public static function removeItem(CoOrder $o, int $itemId): void
    {
        if (! self::editable($o)) {
            return;
        }

        CoOrderItem::where('order_id', $o->id_order)->whereKey($itemId)->whereNull('quotation_item_id')->delete();
    }

    // due_date, due_note, qty_minim boleh di baris mana pun; field harga/qty/deskripsi hanya untuk baris manual.
    public static function updateItem(CoOrder $o, int $itemId, string $field, mixed $value): void
    {
        if (! self::editable($o)) {
            return;
        }

        $item = CoOrderItem::where('order_id', $o->id_order)->findOrFail($itemId);

        if ($field === 'due_date') {
            $item->due_date = $value ?: null;
            $item->save();

            return;
        }

        if ($field === 'due_note') {
            $item->due_note = trim((string) $value) ?: null;
            $item->save();

            return;
        }

        if ($field === 'qty_minim') {
            $item->qty_minim = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            $item->recalcSubtotal();
            $item->save();

            return;
        }

        if ($item->quotation_item_id !== null || ! in_array($field, self::ITEM_FIELDS, true)) {
            return;
        }

        $item->{$field} = $value === '' ? 0 : $value;
        $item->recalcSubtotal();
        $item->updated_by = self::login();
        $item->save();
    }

    public static function addDelivery(CoOrder $o, int $itemId): void
    {
        if (! self::editable($o)) {
            return;
        }

        $item = CoOrderItem::where('order_id', $o->id_order)->where('line_kind', 'PRODUCT')->findOrFail($itemId);

        CoOrderDelivery::create([
            'order_id' => $o->id_order, 'item_id' => $item->id_item, 'shipment_type' => 'FULL', 'ship_qty' => $item->qty,
            'created_by' => self::login(), 'updated_by' => self::login(),
        ]);
    }

    public static function updateDelivery(CoOrder $o, int $deliveryId, string $field, mixed $value): void
    {
        if (! self::editable($o) || ! in_array($field, self::DELIVERY_FIELDS, true)) {
            return;
        }

        CoOrderDelivery::where('order_id', $o->id_order)->whereKey($deliveryId)
            ->update([$field => $value === '' ? null : $value, 'updated_by' => self::login()]);
    }

    public static function removeDelivery(CoOrder $o, int $deliveryId): void
    {
        if (self::editable($o)) {
            CoOrderDelivery::where('order_id', $o->id_order)->whereKey($deliveryId)->delete();
        }
    }

    public static function addExternalCost(CoOrder $o): void
    {
        if (! self::editable($o)) {
            return;
        }

        CoOrderExternalCost::create([
            'order_id' => $o->id_order, 'category' => 'Trucking', 'description' => '', 'nominal' => 0, 'tax_treatment' => 'TAXABLE',
            'created_by' => self::login(), 'updated_by' => self::login(),
        ]);
    }

    public static function updateExternalCost(CoOrder $o, int $costId, string $field, mixed $value): void
    {
        if (! self::editable($o) || ! in_array($field, self::COST_FIELDS, true)) {
            return;
        }

        CoOrderExternalCost::where('order_id', $o->id_order)->whereKey($costId)
            ->update([$field => $value === '' ? null : $value, 'updated_by' => self::login()]);
    }

    public static function removeExternalCost(CoOrder $o, int $costId): void
    {
        if (self::editable($o)) {
            CoOrderExternalCost::where('order_id', $o->id_order)->whereKey($costId)->delete();
        }
    }

    public static function orderType(CoOrder $o): string
    {
        $kinds = $o->items->pluck('line_kind')->unique();

        if ($kinds->count() > 1) {
            return 'Mixed (PRODUCT + SERVICE)';
        }

        return $kinds->first() === 'SERVICE' ? 'Service Only' : 'Product Only';
    }

    // Timeline Inquiry -> Quotation -> CO (tab History), dibangun dari data asli.
    public static function historyTimeline(CoOrder $o): array
    {
        $o->loadMissing('items.inquiry', 'quotation');
        $rows = [];

        foreach ($o->items->pluck('inquiry')->filter()->unique('id_inquiry') as $inq) {
            $rows[] = ['type' => 'Inquiry', 'label' => $inq->inquiry_no.' — '.($inq->product_draft_name ?? '—'), 'date' => $inq->inquiry_date];
        }

        if ($q = $o->quotation) {
            $rows[] = ['type' => 'Quotation', 'label' => $q->quotation_no.' ('.$q->doc_status.')', 'date' => $q->issued_date];
        }
        $rows[] = ['type' => 'Customer Order', 'label' => $o->co_no.' ('.$o->doc_status.')', 'date' => $o->co_date, 'current' => true];

        return $rows;
    }

    private static function login(): string
    {
        return Auth::user()->login;
    }
}
