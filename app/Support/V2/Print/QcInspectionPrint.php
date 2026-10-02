<?php

namespace App\Support\V2\Print;

use App\Models\Pur\PvReceipt;
use Devlib\Locale;
use Devlib\PrintDoc\Document;
use Illuminate\Support\Facades\DB;

// QC Inspection barang masuk (belum A; user 2026-10-02: barang masuk cukup satu dokumen = SPB, yang diperlukan adalah QC
// Inspection). Dicetak dari SPB yang sama: hasil cek jumlah dan mutu per baris, jumlah diterima / ditolak, catatan QC.
// Penanda tangan: Purchasing (menyetujui), Gudang (mengetahui), QC (pemeriksa = yang mencetak).
// Sumber: tm2pur.pv_receipt + pv_line (qc_qty_status, qc_quality_status, c_qty_accepted, c_qty_rejected, qc_note, qc_by, qc_at).
final class QcInspectionPrint extends Document
{
    private const QTY = ['DONE' => 'Checked', 'PENDING' => 'Not yet'];

    private const QUALITY = ['PASS' => 'Pass', 'FAIL' => 'Fail', 'PARTIAL' => 'Partial', 'PENDING' => 'Not yet'];

    public string $type = 'qc-inspection';

    public string $title = 'QC Inspection';

    public ?string $subtitle = 'Incoming Goods Inspection · Internal';

    public string $numberLabel = 'SPB No.';

    public array $refs = [
        ['key' => 'ref_no', 'label' => 'PO / CO No.'],
        ['key' => 'sender_dn', 'label' => 'Sender Delivery Note No.'],
        ['key' => 'warehouse', 'label' => 'Warehouse'],
        ['key' => 'checked_at', 'label' => 'Inspected At'],
        ['key' => 'decision', 'label' => 'Decision'],
    ];

    public string $partyLabel = 'Received From';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

    public string $nb = 'QTY = quantity arrived. Rejected goods do not enter the stock and go back through a Purchase Return.';

    public array $extra = [
        ['key' => 'qty_check', 'label' => 'Qty Check', 'width' => 15],
        ['key' => 'quality', 'label' => 'Quality', 'width' => 15],
        ['key' => 'accepted', 'label' => 'Accepted', 'width' => 17, 'type' => 'money'],
        ['key' => 'rejected', 'label' => 'Rejected', 'width' => 16, 'type' => 'money'],
        ['key' => 'qc_note', 'label' => 'QC Notes', 'width' => 21, 'wrap' => true],
    ];

    public array $signLabels = ['approve' => 'Purchasing', 'know' => 'Warehouse', 'make' => 'QC'];

    public array $signNotes = ['approve' => 'Approved By', 'know' => 'Acknowledged By', 'make' => 'Inspector'];

    public static function load(int $id, array $opt = []): static
    {
        $pv = PvReceipt::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($id);
        $mst = DB::connection('tm2mst');
        $customer = $pv->i_receipt_type === 'CUSTOMER';
        $party = $customer ? $mst->table('cust_customer')->where('id_customer', $pv->i_customer_id)->first() : $mst->table('sup_supplier')->where('id_supplier', $pv->i_supplier_id)->first();
        $ref = $customer ? DB::connection('tm2sdi')->table('co_order')->where('id_order', $pv->i_co_id)->value('co_no') : $pv->c_po_doc_list;
        $warehouse = $pv->i_warehouse_code ? implode(' · ', array_filter([$pv->i_warehouse_code, $mst->table('wh_warehouse')->where('warehouse_code', $pv->i_warehouse_code)->value('warehouse_name')])) : null;
        $lines = $pv->lines;
        $done = $lines->isNotEmpty() && $lines->every(fn ($l) => $l->qc_qty_status === 'DONE' && in_array($l->qc_quality_status, ['PASS', 'FAIL', 'PARTIAL'], true));
        $rejected = (float) $lines->sum('c_qty_rejected');
        $accepted = (float) $lines->sum('c_qty_accepted');
        $decision = ! $done ? 'Inspection not finished' : ($rejected <= 0 ? 'All accepted' : ($accepted <= 0 ? 'All rejected' : 'Partly accepted'));

        return new self([
            'number' => $pv->doc_no ?: 'Draft #'.$pv->pv_id, 'date' => $pv->i_doc_date?->toDateString(), 'status' => $done ? 'QC DONE' : 'QC PENDING',
            'printedBy' => $opt['printedBy'] ?? null, 'ref_no' => $ref, 'sender_dn' => $pv->i_supplier_do_no, 'warehouse' => $warehouse,
            'checked_at' => ($at = $lines->max('qc_at')) ? Locale::dt($at) : null, 'decision' => self::t($decision),
            'contact' => implode(' · ', array_filter([$party->contact_name ?? null, $party->phone ?? null])),
            'party' => ['name' => $party->customer_name ?? $party->supplier_name ?? '', 'lines' => PrintDocs::address($party->default_address ?? null)],
            'notes' => $pv->i_notes ? [['Notes', $pv->i_notes]] : [],
            'rows' => $lines->map(fn ($l) => ['name' => $l->i_description, 'sub' => implode(' · ', array_filter([$l->c_item_code, $l->i_lot_no ? 'Lot '.$l->i_lot_no : null])),
                'qty' => (float) $l->i_qty_purchase, 'unit' => $l->i_uom_purchase,
                'qty_check' => self::t(self::QTY[$l->qc_qty_status ?: 'PENDING'] ?? (string) $l->qc_qty_status),
                'quality' => self::t(self::QUALITY[$l->qc_quality_status ?: 'PENDING'] ?? (string) $l->qc_quality_status),
                'accepted' => $l->c_qty_accepted !== null ? (float) $l->c_qty_accepted : null, 'rejected' => (float) $l->c_qty_rejected,
                'qc_note' => $l->qc_note])->all(),
        ]);
    }
}
