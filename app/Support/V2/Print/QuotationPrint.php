<?php

namespace App\Support\V2\Print;

use App\Models\Master\FinPaymentTerm;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SysConstant;
use App\Services\Inquiry\TierPricingResolver;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Quotation (format A, user 2026-10-02): dokumen ke customer, selalu kop PPN (quotation selalu ber-PPN 11%, EXCL / INCL).
// Total = QuoQuotation::totals() (sama dengan form). Sumber: tm2sdi.quo_quotation + quo_quotation_item + quo_quotation_cc.
// Dicetak hanya setelah diterbitkan (PUBLISHED, aturan cetak v1). Selalu A4 karena membawa lembar lampiran (T10, 2026-10-20):
// Terms & Alternatives (alternatif qty produk utama, termin + rekening, Syarat & Ketentuan) dan Product Mockup (dieline + 3D dari Inquiry).
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
        ['key' => 'rev', 'label' => 'Revision'],
    ];

    public int $infoRows = 7;

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

    public ?QuoQuotation $quotation = null;

    public function paper(): string
    {
        return 'a4';
    }

    public static function load(int $id, array $opt = []): static
    {
        $q = QuoQuotation::with(['customer', 'ccs', 'items' => fn ($i) => $i->orderBy('line_seq'), 'items.inquiry:id_inquiry,inquiry_no'])->findOrFail($id);
        abort_unless($q->doc_status === 'PUBLISHED', 403, t('Print is available once the Quotation is confirmed (issued).'));
        $c = $q->customer;
        $t = $q->totals();
        $doc = new self([
            'number' => $q->quotation_no, 'date' => $q->issued_date?->toDateString(), 'status' => $q->doc_status, 'printedBy' => $opt['printedBy'] ?? null,
            'ppn' => $t['ppn_pct'], 'email' => $c?->email,
            'sums' => ['lines' => [['Subtotal', $t['dpp']], ['VAT', $t['ppn'], 'suffix' => ' '.Locale::decimal($t['ppn_pct'], 2).'%']], 'grand' => $t['grand']],
            'attn' => $q->attn_contact, 'sales' => $q->sales_pic, 'valid_until' => $q->valid_until?->toDateString(),
            'payment_term' => $q->payment_term ? (FinPaymentTerm::where('payment_term_code', $q->payment_term)->value('term_label') ?? $q->payment_term) : null,
            'currency' => $q->currency_code, 'rev' => str_pad((string) $q->revision_no, 2, '0', STR_PAD_LEFT), 'cc' => $q->ccs->sortBy('line_seq')->pluck('contact_name')->filter()->implode(', '),
            'party' => ['name' => $c?->customer_name ?? '', 'code' => $c?->customer_code, 'lines' => PrintDocs::address($c?->default_address)],
            'notes' => $q->notes ? [['Notes', $q->notes]] : [],
            'rows' => $q->items->map(fn ($it) => ['name' => $it->description, 'sub' => $it->spec_note, 'qty' => (float) $it->qty, 'unit' => $it->qty_unit,
                'inquiry_no' => $it->inquiry?->inquiry_no, 'disc' => (float) $it->discount_pct > 0 ? Locale::decimal($it->discount_pct, 2) : null,
                'price' => (float) $it->unit_price, 'subtotal' => (float) $it->subtotal])->all(),
        ]);
        $doc->currency = $q->currency_code ?: 'IDR';
        $doc->quotation = $q;

        return $doc;
    }
    public function appendices(): array
    {
        $q = $this->quotation;
        $incl = $q->tax_scheme === 'INCL';
        $pct = Locale::decimal($q->totals()['ppn_pct'], 0);
        $scheme = self::t($incl ? 'inclusive' : 'exclusive');
        $bank = SysConstant::text('BANK_NAME');
        $bankNo = SysConstant::text('BANK_ACCOUNT_NO');
        $blocks = [
            ['kind' => 'facts', 'title' => 'Payment', 'rows' => array_values(array_filter([
                [self::t('Payment Terms'), $this->data['payment_term'] ?: '—'],
                [self::t('Production lead time'), self::t('Follows capacity')],
                [self::t('Counted from'), self::t('After the proof is approved')],
                [self::t('Transfer to'), $bank || $bankNo
                    ? trim(($bank ?: '—').(SysConstant::text('BANK_ACCOUNT_NAME') ? ' · a.n. '.SysConstant::text('BANK_ACCOUNT_NAME') : '').' · '.self::t('Account no.').' '.($bankNo ?: '—'))
                    : self::t('The payment instruction (bank account) is sent separately by Finance after the PO is issued.')],
            ]))],
            ['kind' => 'list', 'title' => 'Terms & Conditions', 'items' => $q->notes ? [] : [
                self::t('This offer is valid until the date shown in the header.'),
                strtr(self::t('Prices are :scheme of VAT :pct%.'), [':scheme' => $scheme, ':pct' => $pct]),
                self::t('Lead time is counted after the final artwork and the colour digital proof are approved.'),
                self::t('Delivery tolerance is ±5% of the order qty, invoiced by the actual amount delivered.'),
                self::t('This offer becomes binding once the customer purchase order is issued.'),
            ]],
        ];
        if ($q->notes) {
            $blocks[1] = ['kind' => 'text', 'title' => 'Terms & Conditions', 'text' => $q->notes];
        }
        if ($alt = $this->alternatives()) {
            array_unshift($blocks, ['kind' => 'table', 'title' => 'Quantity alternatives (unit price excl. VAT, excluding tooling, main product)',
                'head' => ['Qty option', 'Qty (pcs)', 'Unit price', 'Product subtotal'], 'num' => [1, 2, 3], 'rows' => $alt,
                'note' => 'Other quantity options for the main product of this offer. Tooling is charged once and does not change between options. To choose another option, contact the Sales PIC before the offer expires.']);
        }
        $out = [['title' => 'Terms & Alternatives', 'blocks' => $blocks]];
        // 3 part per lembar A4 supaya kartu tidak terpotong.
        foreach (array_chunk($this->mockupParts(), 3) as $chunk) {
            $out[] = ['title' => 'Product Mockup', 'view' => 'v2.partials.print-quotation-mockup', 'data' => ['parts' => $chunk]];
        }

        return $out;
    }

    // Alternatif jenjang qty (info) dari inquiry produk UTAMA (baris INQUIRY_TIER pertama); bukan bagian harga resmi.
    private function alternatives(): array
    {
        $main = $this->quotation->items->firstWhere('source_type', 'INQUIRY_TIER');
        $inquiry = $main?->inquiry ? IqpInquiry::with(['tiers', 'costing.priceTiers'])->find($main->inquiry_id) : null;
        if (! $inquiry) {
            return [];
        }
        $priced = (bool) ($inquiry->costing?->published_flag) && $inquiry->costing->priceTiers->isNotEmpty();

        return TierPricingResolver::rows($inquiry, $priced)
            ->reject(fn ($r) => (float) $r->tier->qty === (float) $main->qty)->filter(fn ($r) => $r->price_excl !== null)
            ->map(fn ($r) => [Locale::number($r->tier->qty).' pcs', Locale::number($r->tier->qty), Locale::number($r->price_excl), Locale::number($r->value_total)])->values()->all();
    }

    // Lampiran: tiap produk (inquiry) -> tiap part: dieline + 3D dari Inquiry (snapshot iqp_part apa adanya, tidak dihitung ulang).
    private function mockupParts(): array
    {
        $ids = $this->quotation->items->where('source_type', 'INQUIRY_TIER')->pluck('inquiry_id')->filter()->unique();

        return IqpInquiry::with('parts')->whereIn('id_inquiry', $ids)->orderBy('inquiry_no')->get()->flatMap(fn ($inq) => $inq->parts->sortBy('part_seq')->map(function ($part) use ($inq) {
            $spec = $part->dieline_spec;
            $dims = $spec['dims'] ?? [];
            $mm = fn ($k) => (float) ($dims[$k] ?? 0);

            return [
                'title' => $inq->inquiry_no.' · '.$inq->product_draft_name.($inq->parts->count() > 1 ? ' · '.$part->part_name : ''),
                'type' => $spec['type_code'] ?? $part->part_type,
                'size' => collect(['product_length', 'product_width', 'product_height'])->map(fn ($k) => $dims[$k] ?? null)->filter()->map(fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'))->implode(' × '),
                'spec' => $spec ?: null, 'dims' => $dims,
                'box' => ['p' => $mm('product_length') / 10, 'l' => $mm('product_width') / 10, 't' => $mm('product_height') / 10],
                'artwork' => $part->artwork_path ? asset('storage/'.$part->artwork_path) : null,
            ];
        }))->values()->all();
    }
}