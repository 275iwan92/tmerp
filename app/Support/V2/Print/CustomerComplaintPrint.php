<?php

namespace App\Support\V2\Print;

use App\Models\Prd\QcComplaintReview;
use App\Models\Sdi\CrmActivity;
use App\Models\Sdi\CrmComplaint;
use App\Support\V2\Data\CrmComplaints;
use Devlib\PrintDoc\Document;

// Komplain / Retur Penjualan (belum A; user 2026-10-02): lembar keluhan customer yang dibawa Sales ke QA dan disimpan
// sebagai arsip. Tanpa harga, tanpa total. Jawaban QA dan penyelesaian ikut tercetak bila sudah ada.
// Sumber: tm2sdi.crm_complaint + crm_complaint_item + tm2prd.qc_complaint_review + tm2mst.cust_customer.
final class CustomerComplaintPrint extends Document
{
    public string $type = 'customer-complaint';

    public string $title = 'Customer Complaint';

    public string $numberLabel = 'Complaint No.';

    public array $refs = [
        ['key' => 'category', 'label' => 'Category'],
        ['key' => 'received_via', 'label' => 'Received Via'],
        ['key' => 'ref', 'label' => 'Order / Ref.'],
        ['key' => 'requested', 'label' => 'Customer Asks For'],
    ];

    public string $partyLabel = 'Customer';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

    public array $extra = [
        ['key' => 'qty_return', 'label' => 'Qty Returned', 'width' => 24, 'type' => 'int'],
        ['key' => 'problem', 'label' => 'Problem', 'width' => 60, 'wrap' => true],
    ];

    public array $signLabels = ['approve' => 'Approved By', 'know' => 'Quality Assurance', 'make' => 'Prepared By'];

    public array $signNotes = ['approve' => '', 'know' => '', 'make' => 'Sales'];

    public static function load(int $id, array $opt = []): static
    {
        $c = CrmComplaints::find($id) ?? abort(404);
        abort_if($c->status() === 'DRAFT', 404);
        $cust = $c->customer;
        $rv = $c->review?->stat_confirm ? $c->review : null;
        $doc = new self([
            'number' => $c->complaint_no, 'date' => $c->complaint_date?->toDateString(), 'status' => $c->status(), 'printedBy' => $opt['printedBy'] ?? null,
            'category' => self::t(CrmComplaint::CATEGORIES[$c->category] ?? $c->category),
            'received_via' => $c->received_via ? self::t(CrmActivity::TYPES[$c->received_via] ?? $c->received_via) : null,
            'ref' => $c->order?->co_no ?: $c->ref_doc_no,
            'requested' => $c->requested_action ? self::t(CrmComplaint::REQUESTS[$c->requested_action] ?? $c->requested_action) : null,
            'contact' => implode(' · ', array_filter([$c->contact_person, $cust?->phone])), 'email' => $cust?->email,
            'party' => ['name' => $cust?->customer_name ?? '', 'lines' => PrintDocs::address($cust?->default_address, $cust?->postal_code)],
            'notes' => array_values(array_filter([
                $c->description ? ['Complaint', $c->description] : null,
                $rv ? ['QA Verdict', self::t(QcComplaintReview::VERDICTS[$rv->verdict] ?? '').($rv->root_cause ? ' — '.$rv->root_cause : '')] : null,
                $rv?->corrective_action ? ['Corrective Action', $rv->corrective_action] : null,
                $c->resolution ? ['Resolution', self::t(CrmComplaint::RESOLUTIONS[$c->resolution] ?? $c->resolution).($c->resolution_note ? ' — '.$c->resolution_note : '')] : null,
                $c->stat_cancel ? ['Cancel Reason', (string) $c->cancel_reason] : null,
            ])),
            'rows' => $c->items->map(fn ($i) => ['name' => $i->product_name, 'qty' => (float) $i->qty_complaint, 'unit' => $i->qty_unit,
                'qty_return' => (float) $i->qty_return, 'problem' => $i->defect_note])->all(),
        ]);
        if ($c->complaint_type === 'RETURN') {
            $doc->title = 'Sales Return';
        }

        return $doc;
    }
}
