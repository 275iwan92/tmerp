<?php

namespace App\Support\V2\Print;

use App\Models\Pur\PvReceipt;
use Devlib\PrintDoc\Document;
use Illuminate\Support\Facades\DB;

// Surat Penerimaan Barang / SPB (format A, user 2026-10-02): dokumen gudang, TANPA harga. Qty = jumlah datang; Diterima /
// Ditolak = hasil QC. Sumber: tm2pur.pv_receipt + pv_line.
// Tiga jenis penerimaan = tiga judul dan tiga seri nomor (user 2026-10-02): dari supplier = "Surat Penerimaan Barang" (SPB),
// bahan dari customer = "Penerimaan Bahan Baku Customer" (PBC), kembali dari subkon = "Penerimaan Barang dari Subkon" (PBS).
// Karena judulnya sudah berbeda, field "Jenis" tidak dicetak. SPB dipakai accounting untuk memeriksa tagihan supplier,
// jadi nomornya harus urut tanpa diselingi dua jenis lainnya.
final class GoodsReceiptPrint extends Document
{
    // jenis => [judul, sub-judul, label nomor, label rujukan, penyerah, NB]
    private const KINDS = [
        'PO' => ['Goods Receipt', 'SPB · Goods Receipt', 'SPB No.', 'PO No.', 'Supplier / Forwarder',
            'QTY = quantity arrived. Rejected goods are returned or wait for a Purchasing decision.'],
        'CUSTOMER' => ['Customer Material Receipt', 'PBC · Customer Material Receipt', 'Receiving No.', 'CO No.', 'Customer / Forwarder',
            'Material owned by the customer, for the Customer Order above only. Not company stock.'],
        'SUBCON' => ['Goods Receipt from Subcon', 'PBS · Goods Receipt from Subcon', 'Receiving No.', 'PO No.', 'Subcontractor / Forwarder',
            'QTY = quantity returned by the subcontractor. A shortage is settled with the subcontractor.'],
    ];

    public string $type = 'goods-receipt';

    public string $title = 'Goods Receipt';

    public ?string $subtitle = 'SPB · Goods Receipt';

    public string $numberLabel = 'SPB No.';

    public array $refs = [
        ['key' => 'ref_no', 'label' => 'PO No.'],
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

    // Label nomor dokumen menurut jenis penerimaan (dipakai juga cetakan QC Inspection).
    public static function numberLabelFor(?string $receiptType): string
    {
        return (self::KINDS[$receiptType] ?? self::KINDS['PO'])[2];
    }

    public static function referenceLabelFor(?string $receiptType): string
    {
        return (self::KINDS[$receiptType] ?? self::KINDS['PO'])[3];
    }

    public static function load(int $id, array $opt = []): static
    {
        $pv = PvReceipt::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($id);
        $mst = DB::connection('tm2mst');
        $customer = $pv->i_receipt_type === 'CUSTOMER';
        $party = $customer ? $mst->table('cust_customer')->where('id_customer', $pv->i_customer_id)->first() : $mst->table('sup_supplier')->where('id_supplier', $pv->i_supplier_id)->first();
        $ref = $customer ? DB::connection('tm2sdi')->table('co_order')->where('id_order', $pv->i_co_id)->value('co_no') : $pv->c_po_doc_list;
        $warehouse = $pv->i_warehouse_code ? implode(' · ', array_filter([$pv->i_warehouse_code, $mst->table('wh_warehouse')->where('warehouse_code', $pv->i_warehouse_code)->value('warehouse_name')])) : null;

        $doc = new self([
            'number' => $pv->doc_no ?: 'Draft #'.$pv->pv_id, 'date' => $pv->i_doc_date?->toDateString(), 'status' => $pv->status, 'printedBy' => $opt['printedBy'] ?? null,
            'ref_no' => $ref, 'sender_dn' => $pv->i_supplier_do_no,
            'our_dn' => $pv->i_do_id ? DB::connection('tm2sdi')->table('do_delivery')->where('id_delivery', $pv->i_do_id)->value('do_no') : null,
            'vehicle' => $pv->i_vehicle_no, 'warehouse' => $warehouse, 'email' => $party->email ?? null,
            'contact' => implode(' · ', array_filter([$party->contact_name ?? null, $party->phone ?? null])),
            'party' => ['name' => $party->customer_name ?? $party->supplier_name ?? '', 'lines' => PrintDocs::address($party->default_address ?? null)],
            'notes' => $pv->i_notes ? [['Notes', $pv->i_notes]] : [],
            'rows' => $pv->lines->map(fn ($l) => ['name' => $l->i_description, 'sub' => $l->c_item_code, 'qty' => (float) $l->i_qty_purchase, 'unit' => $l->i_uom_purchase,
                'accepted' => $l->c_qty_accepted !== null ? (float) $l->c_qty_accepted : null, 'rejected' => (float) $l->c_qty_rejected > 0 ? (float) $l->c_qty_rejected : null,
                'lot_no' => $l->i_lot_no, 'remark' => $l->qc_note ?: $l->i_short_note])->all(),
        ]);
        $kind = array_key_exists($pv->i_receipt_type, self::KINDS) ? $pv->i_receipt_type : 'PO';
        [$doc->title, $doc->subtitle, $doc->numberLabel, $refLabel, $sender, $doc->nb] = self::KINDS[$kind];
        $doc->titleCompact = $kind !== 'PO';
        $doc->refs[0]['label'] = $refLabel;
        $doc->receivers = [['Sender', $sender]];
        if ($kind === 'SUBCON') {
            array_splice($doc->refs, 1, 0, [['key' => 'our_dn', 'label' => 'Our Delivery Note No.']]);
        }

        return $doc;
    }
}
