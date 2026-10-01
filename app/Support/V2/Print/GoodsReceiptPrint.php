<?php

namespace App\Support\V2\Print;

use App\Models\Pur\PvReceipt;
use Devlib\PrintDoc\Document;
use Illuminate\Support\Facades\DB;

// Penerimaan Barang / SPB (belum A): dokumen gudang, TANPA harga, untuk ketiga jenis penerimaan (dari supplier, kembali
// dari subkon, bahan dari customer). Qty = jumlah datang; Diterima / Ditolak = hasil QC. Sumber: tm2pur.pv_receipt + pv_line.
final class GoodsReceiptPrint extends Document
{
    private const KINDS = ['PO' => 'From Supplier', 'SUBCON' => 'Return from Subcon', 'CUSTOMER' => 'Material from Customer'];

    public string $type = 'goods-receipt';

    public string $title = 'Goods Receipt';

    public ?string $subtitle = 'SPB · Goods Receipt';

    public string $numberLabel = 'SPB No.';

    public array $refs = [
        ['key' => 'receipt_type', 'label' => 'Kind'],
        ['key' => 'ref_no', 'label' => 'PO / CO No.'],
        ['key' => 'sender_dn', 'label' => 'Sender Delivery Note No.'],
        ['key' => 'vehicle', 'label' => 'Vehicle No.'],
        ['key' => 'warehouse', 'label' => 'Warehouse'],
    ];

    public string $partyLabel = 'Received From';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

    public string $nb = 'QTY = quantity arrived. Rejected goods are returned or wait for a Purchasing decision.';

    public array $extra = [
        ['key' => 'accepted', 'label' => 'Accepted', 'width' => 19, 'type' => 'money'],
        ['key' => 'rejected', 'label' => 'Rejected', 'width' => 19, 'type' => 'money'],
        ['key' => 'lot_no', 'label' => 'Lot / Roll', 'width' => 21],
        ['key' => 'remark', 'label' => 'Remarks', 'width' => 25],
    ];

    public array $receivers = [['Sender', 'Supplier / Forwarder']];

    public static function load(int $id, array $opt = []): static
    {
        $pv = PvReceipt::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($id);
        $mst = DB::connection('tm2mst');
        $customer = $pv->i_receipt_type === 'CUSTOMER';
        $party = $customer ? $mst->table('cust_customer')->where('id_customer', $pv->i_customer_id)->first() : $mst->table('sup_supplier')->where('id_supplier', $pv->i_supplier_id)->first();
        $ref = $customer ? DB::connection('tm2sdi')->table('co_order')->where('id_order', $pv->i_co_id)->value('co_no') : $pv->c_po_doc_list;
        $warehouse = $pv->i_warehouse_code ? implode(' · ', array_filter([$pv->i_warehouse_code, $mst->table('wh_warehouse')->where('warehouse_code', $pv->i_warehouse_code)->value('warehouse_name')])) : null;

        return new self([
            'number' => $pv->doc_no ?: 'Draft #'.$pv->pv_id, 'date' => $pv->i_doc_date?->toDateString(), 'status' => $pv->status, 'printedBy' => $opt['printedBy'] ?? null,
            'receipt_type' => self::t(self::KINDS[$pv->i_receipt_type] ?? $pv->i_receipt_type), 'ref_no' => $ref, 'sender_dn' => $pv->i_supplier_do_no,
            'vehicle' => $pv->i_vehicle_no, 'warehouse' => $warehouse, 'email' => $party->email ?? null,
            'contact' => implode(' · ', array_filter([$party->contact_name ?? null, $party->phone ?? null])),
            'party' => ['name' => $party->customer_name ?? $party->supplier_name ?? '', 'lines' => PrintDocs::address($party->default_address ?? null)],
            'notes' => $pv->i_notes ? [['Notes', $pv->i_notes]] : [],
            'rows' => $pv->lines->map(fn ($l) => ['name' => $l->i_description, 'sub' => $l->c_item_code, 'qty' => (float) $l->i_qty_purchase, 'unit' => $l->i_uom_purchase,
                'accepted' => $l->c_qty_accepted !== null ? (float) $l->c_qty_accepted : null, 'rejected' => (float) $l->c_qty_rejected > 0 ? (float) $l->c_qty_rejected : null,
                'lot_no' => $l->i_lot_no, 'remark' => $l->qc_note ?: $l->i_short_note])->all(),
        ]);
    }
}
