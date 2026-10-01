<?php

namespace App\Support\V2\Print;

use App\Models\Master\FinCurrency;
use App\Models\Pur\PqQuotation;
use Devlib\Locale;
use Devlib\PrintDoc\Document;
use Illuminate\Support\Facades\DB;

// Purchase Quotation (belum A): catatan penawaran supplier untuk dibandingkan, dokumen internal. Nilai dalam IDR
// (c_price_idr / c_subtotal_idr, hasil kurs). Sumber: tm2pur.pq_quotation + pq_line.
final class PurchaseQuotationPrint extends Document
{
    public string $type = 'purchase-quotation';

    public string $title = 'Purchase Quotation';

    public ?string $subtitle = 'Supplier Quotation · Internal';

    public string $numberLabel = 'PQ No.';

    public array $refs = [
        ['key' => 'pr_no', 'label' => 'PR Ref.'],
        ['key' => 'valid_until', 'label' => 'Valid Until', 'type' => 'date'],
        ['key' => 'payment_term', 'label' => 'Payment Terms'],
        ['key' => 'currency', 'label' => 'Currency / Rate'],
        ['key' => 'lead_time', 'label' => 'Lead Time'],
    ];

    public string $partyLabel = 'Supplier';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

    public string $nb = 'Record of a supplier quotation for comparison. Not an order; orders are issued as a Purchase Order.';

    public array $extra = [
        ['key' => 'pr_line', 'label' => 'PR Line', 'width' => 17],
        ['key' => 'lead_days', 'label' => 'Lead (days)', 'width' => 21, 'type' => 'int'],
    ];

    public bool $priced = true;

    public static function load(int $id, array $opt = []): static
    {
        $pq = PqQuotation::with(['requisition', 'lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($id);
        $sup = $pq->i_supplier_id ? DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $pq->i_supplier_id)->first() : null;
        $prLines = DB::connection('tm2pur')->table('pr_line')->whereIn('pr_line_id', $pq->lines->pluck('pr_line_id')->filter()->all())->pluck('line_no', 'pr_line_id');
        $code = $pq->i_currency_id ? FinCurrency::where('id_currency', $pq->i_currency_id)->value('currency_code') : 'IDR';
        $rate = (float) $pq->i_fx_rate;

        return new self([
            'number' => $pq->doc_no ?: 'Draft #'.$pq->pq_id, 'date' => $pq->i_doc_date?->toDateString(), 'status' => $pq->status, 'printedBy' => $opt['printedBy'] ?? null,
            'sums' => ['lines' => [], 'grand' => (float) $pq->lines->sum('c_subtotal_idr')],
            'pr_no' => $pq->requisition?->doc_no, 'valid_until' => $pq->i_quotation_valid_until?->toDateString(), 'payment_term' => $pq->i_payment_term,
            'currency' => $code.($rate > 0 && $rate != 1.0 ? ' · '.Locale::decimal($rate, 4) : ''),
            'lead_time' => $pq->i_lead_time_day !== null ? Locale::number($pq->i_lead_time_day, 0).' '.self::t('days') : null,
            'contact' => implode(' · ', array_filter([$sup->contact_name ?? null, $sup->phone ?? null])),
            'party' => ['name' => $sup->supplier_name ?? ($pq->i_supplier_text ?? ''), 'lines' => PrintDocs::address($sup->default_address ?? null)],
            'notes' => $pq->i_notes ? [['Notes', $pq->i_notes]] : [],
            'rows' => $pq->lines->map(fn ($l) => ['name' => $l->i_description, 'qty' => (float) $l->i_qty, 'unit' => $l->i_uom, 'pr_line' => $prLines[$l->pr_line_id] ?? null,
                'lead_days' => $l->i_lead_time_day, 'price' => (float) $l->c_price_idr, 'subtotal' => (float) $l->c_subtotal_idr])->all(),
        ]);
    }
}
