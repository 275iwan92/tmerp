<?php

namespace App\Support\V2\Print;

use App\Models\Master\FinPaymentTerm;
use App\Models\Sdi\QuoQuotation;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Quotation (belum A): dokumen ke customer, selalu kop PPN (quotation selalu ber-PPN 11%, EXCL / INCL).
// Total = QuoQuotation::totals() (sama dengan form). Sumber: tm2sdi.quo_quotation + quo_quotation_item + quo_quotation_cc.
final class QuotationPrint extends Document
{
    public string $type = 'quotation';

    public string $title = 'Quotation';

    public ?string $subtitle = 'Price Quotation';

    public string $numberLabel = 'Quotation No.';

    public array $refs = [
        ['key' => 'attn', 'label' => 'Attn'],
        ['key' => 'sales', 'label' => 'Sales'],
        ['key' => 'valid_until', 'label' => 'Valid Until', 'type' => 'date'],
        ['key' => 'payment_term', 'label' => 'Payment Terms'],
        ['key' => 'currency', 'label' => 'Currency'],
    ];

    public string $partyLabel = 'To';

    public ?array $side = ['key' => 'cc', 'label' => 'CC'];

    public string $nb = 'Dear Sir / Madam, we are pleased to submit our price quotation as follows:';

    public bool $nbPlain = true;

    public string $nameLabel = 'Product / Specification';

    public array $extra = [
        ['key' => 'inquiry_no', 'label' => 'Inquiry No.', 'width' => 21],
        ['key' => 'disc', 'label' => 'Disc. %', 'width' => 17],
    ];

    public bool $priced = true;

    public array $receivers = [['Customer', 'Approves the quotation']];

    public static function load(int $id, array $opt = []): static
    {
        $q = QuoQuotation::with(['customer', 'ccs', 'items' => fn ($i) => $i->orderBy('line_seq'), 'items.inquiry:id_inquiry,inquiry_no'])->findOrFail($id);
        $c = $q->customer;
        $t = $q->totals();
        $doc = new self([
            'number' => $q->quotation_no, 'date' => $q->issued_date?->toDateString(), 'status' => $q->doc_status, 'printedBy' => $opt['printedBy'] ?? null,
            'ppn' => $t['ppn_pct'], 'email' => $c?->email,
            'sums' => ['lines' => [['Subtotal', $t['dpp']], ['VAT', $t['ppn'], 'suffix' => ' '.Locale::decimal($t['ppn_pct'], 2).'%']], 'grand' => $t['grand']],
            'attn' => $q->attn_contact, 'sales' => $q->sales_pic, 'valid_until' => $q->valid_until?->toDateString(),
            'payment_term' => $q->payment_term ? (FinPaymentTerm::where('payment_term_code', $q->payment_term)->value('term_label') ?? $q->payment_term) : null,
            'currency' => $q->currency_code, 'cc' => $q->ccs->sortBy('line_seq')->pluck('contact_name')->filter()->implode(', '),
            'party' => ['name' => $c?->customer_name ?? '', 'code' => $c?->customer_code, 'lines' => PrintDocs::address($c?->default_address)],
            'notes' => $q->notes ? [['Notes', $q->notes]] : [],
            'rows' => $q->items->map(fn ($it) => ['name' => $it->description, 'sub' => $it->spec_note, 'qty' => (float) $it->qty, 'unit' => $it->qty_unit,
                'inquiry_no' => $it->inquiry?->inquiry_no, 'disc' => (float) $it->discount_pct > 0 ? Locale::decimal($it->discount_pct, 2) : null,
                'price' => (float) $it->unit_price, 'subtotal' => (float) $it->subtotal])->all(),
        ]);
        $doc->currency = $q->currency_code ?: 'IDR';

        return $doc;
    }
}
