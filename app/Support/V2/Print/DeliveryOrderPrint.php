<?php

/*
 * REMARK
 * Tujuan       : Cetak Delivery Order (format A, memuat harga); tarif PPN dibaca menurut tanggal DO.
 * Sumber hulu  : do_delivery + do_line (tm2sdi), TaxRate::ppn(tanggal) (layar Tax Rate).
 * Input        : id DO, opsi pencetak.
 * Output       : Dokumen cetak DO; harga hanya untuk yang berhak melihat harga jual.
 * Dipakai oleh : Rute cetak v2 (PrintDocs), tombol Print di layar Delivery Order.
 * Status       : Aktif.
 */

namespace App\Support\V2\Print;

use App\Models\Sdi\DoDelivery;
use App\Support\Finance\TaxRate;
use Devlib\PrintDoc\Document;
use Illuminate\Support\Facades\Auth;

// Delivery Order (format A, user 2026-10-02): dasar Surat Jalan dan penagihan, memuat harga -- hanya untuk user yang berhak melihat harga jual
// (yang lain memakai Surat Jalan, tanpa harga). Sumber: tm2sdi.do_delivery + do_line.
final class DeliveryOrderPrint extends Document
{
    public string $type = 'delivery-order';

    public string $title = 'Delivery Order';

    public ?string $subtitle = 'Delivery Instruction · Internal';

    public string $numberLabel = 'DO No.';

    public array $refs = [
        ['key' => 'do_type', 'label' => 'DO Kind'],
        ['key' => 'planned_date', 'label' => 'Planned Shipment', 'type' => 'date'],
        ['key' => 'actual_date', 'label' => 'Actual Ship Date', 'type' => 'date', 'hand' => true],
        ['key' => 'tax', 'label' => 'Tax'],
        ['key' => 'region', 'label' => 'Destination Area'],
    ];

    public string $partyLabel = 'Ship To';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn', 'hand' => true];

    public string $nb = 'Basis of the Delivery Note and invoicing. Lines come from the Customer Plan Delivery that is already Posted.';

    public string $nameLabel = 'Product Name';

    public array $extra = [
        ['key' => 'co_no', 'label' => 'CO No.', 'width' => 17],
        ['key' => 'price_source', 'label' => 'Price From', 'width' => 21],
    ];

    public bool $priced = true;

    public array $receivers = [['Warehouse', 'Prepares the goods']];

    public static function load(int $id, array $opt = []): static
    {
        abort_unless(Auth::user()?->canViewSalesPrice() ?? true, 403);
        $do = DoDelivery::with(['customer', 'supplier', 'lines' => fn ($q) => $q->where('status', '<>', 'CANCELLED')->orderBy('line_no'), 'lines.order:id_order,co_no'])->findOrFail($id);
        $party = $do->customer ?? $do->supplier;

        $doc = new self([
            'number' => $do->do_no ?: 'Draft #'.$do->id_delivery, 'date' => $do->do_date?->toDateString(), 'status' => $do->status, 'printedBy' => $opt['printedBy'] ?? null,
            'ppn' => $do->is_ppn ? TaxRate::ppn($do->do_date) : 0, 'email' => $party?->email,
            'do_type' => $do->do_type, 'planned_date' => $do->scheduled_ship_date?->toDateString(), 'actual_date' => $do->actual_ship_date?->toDateString(),
            'tax' => $do->is_ppn ? 'PPN' : 'Non PPN', 'region' => $do->ship_address_label,
            'contact' => implode(' · ', array_filter([$party?->contact_name, $party?->phone])),
            'party' => ['name' => $party?->customer_name ?? $party?->supplier_name ?? '', 'lines' => array_values(array_filter([...PrintDocs::address($do->ship_address_detail, $do->ship_address_label, $do->ship_postal_code), $do->pickupNote()]))],
            'notes' => $do->notes ? [['Notes', $do->notes]] : [],
            'rows' => $do->lines->map(fn ($l) => ['name' => $l->item_description, 'qty' => (float) $l->qty_do, 'unit' => $l->qty_unit, 'co_no' => $l->order?->co_no,
                'price_source' => $l->price_overridden ? 'Override' : 'CO', 'price' => (float) $l->unit_price, 'subtotal' => (float) $l->subtotal])->all(),
        ]);
        // Satu baris alamat tambahan untuk keterangan Pickup, supaya alamat customer tidak terpotong.
        $doc->infoRows += $do->pickupNote() ? 1 : 0;

        return $doc;
    }
}
