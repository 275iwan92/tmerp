<?php

/*
 * REMARK
 * Tujuan       : Cetak Nota Inquiry v2 (pengganti pages::inquiry.nota): penawaran harga per tier qty ke customer (harga hanya bila costing PUBLISHED), lampiran Product Specification, Full-Size Dieline, Terms & Customer Confirmation.
 * Sumber hulu  : iqp_inquiry (+ part, material, process, tier, tooling, costing + price tier), TierPricingResolver, TaxRate::ppn, PrintDocs::company (kop mengikuti skema pajak).
 * Input        : id inquiry (+ opsi printedBy).
 * Output       : Objek Devlib\PrintDoc\Document (A4) untuk x-dl::print-doc; hanya baca, tidak menulis data.
 * Dipakai oleh : PrintDocs::MAP['inquiry-note'] (route v2.print), layar daftar nota-inquiry, tombol Print Note di Inquiry New.
 * Status       : Aktif (T10 pindahan v1, 2026-10-20).
 */

namespace App\Support\V2\Print;

use App\Models\Master\ProdType;
use App\Models\Sdi\IqpInquiry;
use App\Services\Inquiry\TierPricingResolver;
use App\Support\Finance\TaxRate;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Nota Inquiry (v1 "NOTA INQUIRY"): dokumen untuk customer. Beda dari InquiryPrint (internal, tanpa harga). Satu baris = satu tier qty.
// Sisi A/B (CLAUDE.md §4): harga jual hanya tercetak bila costing published_flag = 1 dan punya price tier (aturan v1 yang sama).
final class InquiryNotePrint extends Document
{
    public string $type = 'inquiry-note';

    public string $title = 'Inquiry Note';

    public ?string $subtitle = 'Price Offer · Product Specification';

    public string $numberLabel = 'Inquiry No.';

    public array $refs = [
        ['key' => 'inquiry_type', 'label' => 'Kind'],
        ['key' => 'rev', 'label' => 'Revision'],
        ['key' => 'source', 'label' => 'Source'],
        ['key' => 'sales', 'label' => 'Sales'],
        ['key' => 'deadline_quotation', 'label' => 'Quotation Deadline', 'type' => 'date'],
        ['key' => 'currency', 'label' => 'Currency'],
    ];

    public int $infoRows = 7;

    public string $partyLabel = 'To';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

    public bool $nbPlain = true;

    public bool $nbRaw = true;

    public string $nameLabel = 'Price Tier';

    public array $extra = [
        ['key' => 'target', 'label' => 'Target', 'width' => 19, 'type' => 'money'],
        ['key' => 'incl', 'label' => 'Incl. VAT', 'width' => 19, 'type' => 'money'],
    ];

    public bool $priced = true;

    public array $receivers = [['Customer', 'Confirms the note']];

    public ?IqpInquiry $inquiry = null;

    public function paper(): string
    {
        return 'a4';
    }

    // Qty tier = pcs utuh.
    public function qty(mixed $v): string
    {
        return $v === null || $v === '' ? '' : Locale::number($v, 0);
    }

    // Satu baris nilai: order tier utama (tanpa terbilang; jumlah semua tier bukan nilai order).
    public function totals(): array
    {
        $v = $this->data['order_value'] ?? null;

        return ['lines' => $this->priced && $v !== null ? [[self::t('Order value, primary tier'), Locale::number($v, 2), true]] : [], 'grand' => null];
    }

    public static function load(int $id, array $opt = []): static
    {
        $q = IqpInquiry::with(['customer', 'product', 'parts' => fn ($p) => $p->orderBy('part_seq'), 'parts.materials', 'parts.processes', 'tiers', 'costing.priceTiers', 'toolings'])->findOrFail($id);
        $c = $q->customer;
        $priced = (bool) ($q->costing?->published_flag) && $q->costing->priceTiers->isNotEmpty();
        $tiers = TierPricingResolver::rows($q, $priced);
        $primary = TierPricingResolver::primary($tiers);
        $ppnPct = $q->tax_scheme === 'PPN' ? (float) ($q->costing?->ppn_pct ?? $q->ppn_percent ?? TaxRate::ppn($q->inquiry_date)) : 0;
        $spec = fn ($part) => is_array($part->dieline_spec) ? $part->dieline_spec : (json_decode((string) $part->dieline_spec, true) ?: []);
        $typeCode = $q->parts->map(fn ($p) => $spec($p)['type_code'] ?? null)->filter()->first();
        $name = $c?->customer_name ?? ($q->prospect_name ?? '');
        $doc = new self([
            'number' => $q->inquiry_no, 'date' => $q->inquiry_date?->toDateString(), 'status' => $q->stat_cancel ? 'CANCELLED' : $q->sub_state, 'printedBy' => $opt['printedBy'] ?? null,
            'ppn' => $ppnPct, 'email' => $c?->email,
            'inquiry_type' => $q->stat_cancel ? 'CANCELLED' : trim($q->inquiry_type.($q->is_prospect ? ' · PROSPECT' : '')),
            'rev' => $q->revision_no > 0 ? str_pad((string) $q->revision_no, 2, '0', STR_PAD_LEFT) : '00',
            'source' => $q->source, 'sales' => $q->pic_sales, 'deadline_quotation' => $q->deadline_quotation?->toDateString(),
            'currency' => $q->costing?->currency_code ?? 'IDR',
            'contact' => implode(' · ', array_filter([$c?->contact_name, $c?->phone])),
            'party' => ['name' => $name, 'code' => $c?->customer_code, 'lines' => PrintDocs::address($c?->default_address)],
            'order_value' => $priced ? $primary?->value_total : null,
            'notes' => array_values(array_filter([
                ['Product', trim(($q->product_draft_name ?? $q->product?->product_name ?? '—').' · '.$q->parts->count().' part'.($typeCode ? ' · '.(ProdType::where('type_code', $typeCode)->value('type_name') ?? $typeCode) : ''))],
                $q->sales_note ? ['Sales Notes', $q->sales_note] : null,
            ])),
            'rows' => $tiers->values()->map(fn ($r, $i) => ['name' => self::t('Tier').' '.($i + 1).($r->tier->is_primary ? ' ★' : ''), 'qty' => (float) $r->tier->qty, 'unit' => 'PCS',
                'target' => $priced && $r->tier->price_excl !== null ? (float) $r->tier->price_excl : null,
                'incl' => $priced ? $r->price_incl : null, 'price' => $priced ? $r->price_excl : null, 'subtotal' => $priced ? $r->value_total : null])->all(),
        ]);
        $doc->priced = $priced;
        $doc->extra = $priced ? $doc->extra : [];
        $doc->inquiry = $q;
        $pct = Locale::decimal($ppnPct ?: TaxRate::ppn($q->inquiry_date), 0);
        $doc->nb = $priced
            ? strtr(self::t('Price per pcs, :cur · VAT :pct%. A change of material, gram weight, colours or finishing changes the price.'), [':cur' => $doc->currency, ':pct' => $pct])
            : self::t('Price not available yet (costing not published): technical specification only. Ask Costing for the official offer.');

        return $doc;
    }

    public function appendices(): array
    {
        $q = $this->inquiry;
        $parts = $this->parts();
        $blocks = [];
        foreach ($parts as $p) {
            $blocks[] = ['kind' => 'facts', 'title' => $p['title'], 'rows' => $p['rows']];
        }
        $info = array_values(array_filter([
            $q->design_number ? ['Design No.', $q->design_number] : null,
            $q->material_source ? ['Material source', $q->material_source] : null,
            $q->job_nature ? ['Job nature', $q->job_nature] : null,
        ]));
        if ($info) {
            $blocks[] = ['kind' => 'facts', 'title' => 'Additional Info', 'rows' => $info];
        }
        $out = [['title' => 'Product Specification', 'blocks' => $blocks ?: [['kind' => 'text', 'title' => 'Product Specification', 'text' => self::t('There is no part in this inquiry yet.')]]]];
        foreach (array_chunk(array_values(array_filter($parts, fn ($p) => $p['spec'])), 3) as $chunk) {
            $out[] = ['title' => 'Full-Size Dieline', 'view' => 'v2.partials.print-inquiry-note-dieline', 'data' => ['parts' => $chunk]];
        }
        $out[] = ['title' => 'Terms & Customer Confirmation', 'blocks' => $this->termsBlocks()];

        return $out;
    }

    private function parts(): array
    {
        $num = fn ($v, $d = 0) => $v === null || $v === '' ? '—' : Locale::number($v, $d);

        return $this->inquiry->parts->map(function ($part) use ($num) {
            $spec = is_array($part->dieline_spec) ? $part->dieline_spec : (json_decode((string) $part->dieline_spec, true) ?: []);
            $d = $spec['dims'] ?? [];
            $mat = $part->materials->firstWhere('is_primary', true) ?? $part->materials->first();
            $code = $spec['type_code'] ?? $part->part_type;

            return [
                'title' => 'Part '.$part->part_seq.' — '.($part->part_name ?: 'Part #'.$part->part_seq).($code ? ' / '.$code : ''),
                'spec' => $spec ?: null, 'dims' => $d,
                'caption' => trim('Part '.$part->part_seq.' — '.($part->part_name ?: '').' · '.Locale::number((float) $part->flat_w, 0).' × '.Locale::number((float) $part->flat_h, 0).' mm'),
                'rows' => array_values(array_filter([
                    ['Dimensions L × W × H', $num($d['product_length'] ?? null, 2).' × '.$num($d['product_width'] ?? null, 2).' × '.$num($d['product_height'] ?? null, 2).' mm'],
                    ! empty($d['glue_flap']) ? ['Glue flap', $num($d['glue_flap'], 2).' mm'] : null,
                    ['Flat spread', Locale::number((float) $part->flat_w, 0).' × '.Locale::number((float) $part->flat_h, 0).' mm'],
                    $part->flat_area ? ['Flat area', Locale::number(round(((float) $part->flat_area) / 1_000_000, 4), 3).' m²'] : null,
                    ['Material', ($mat?->rawmat_name ?? '—').($mat?->gram_weight ? ' '.$mat->gram_weight.' gsm' : '')],
                    ['Colour', ($part->cfg_color ?? '—').($part->cfg_ink ? ' ('.$part->cfg_ink.')' : '')],
                    ['Process / Finishing', $part->processes->sortBy('step_seq')->pluck('process_name')->filter()->implode(', ') ?: '—'],
                ])),
            ];
        })->all();
    }

    private function termsBlocks(): array
    {
        $q = $this->inquiry;
        $tooling = (float) $q->toolings->sum('cost');
        $tiers = $q->tiers->sortBy('tier_seq')->map(fn ($t) => Locale::number($t->qty, 0))->implode(' · ');

        return [
            ['kind' => 'list', 'title' => 'Terms & Conditions', 'items' => array_values(array_filter([
                self::t('The price is valid for 30 days from the issue date of this note.'),
                self::t('Delivery tolerance is ±5% of the order qty (printing industry standard).'),
                self::t('Lead time follows the production capacity after the final artwork and the digital proof are approved.'),
                $tooling > 0 ? strtr(self::t('Tooling cost (knife / die / plate) of :amount is charged once on the first order.'), [':amount' => 'IDR '.Locale::number($tooling, 0)]) : null,
                self::t('Payment follows the payment term of the registered customer / down payment for the first order.'),
                self::t('Prices exclude shipping cost unless stated otherwise.'),
                self::t('A change of specification after the note is approved can change the price and lead time.'),
            ]))],
            ['kind' => 'sign', 'title' => 'Customer Confirmation',
                'intro' => 'Please check the price and the specification. If they are correct, sign below and return this note to the Sales PIC. If there is a correction, write it on this document — we will issue a revision.',
                'checks' => [self::t('AGREED — continue to the official quotation, qty tier:').' '.$tiers, self::t('Needs REVISION — notes:').' ........................................'],
                'slots' => [['Customer', 'Name, position & stamp'], ['Sales', $q->pic_sales ?? ''], ['Acknowledged By', 'Sales Manager']]],
        ];
    }
}
