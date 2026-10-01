<?php

namespace App\Support\V2\Print;

use App\Models\Master\ProdType;
use App\Models\Sdi\IqpInquiry;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Inquiry (format A, user 2026-10-01): dokumen internal tanpa harga, dipakai Inquiry Baru / Revisi / Rekalkulasi (field
// Jenis). Satu baris per part. Sumber: tm2sdi.iqp_inquiry + iqp_part + iqp_part_material + iqp_part_process + iqp_tier.
final class InquiryPrint extends Document
{
    public string $type = 'inquiry';

    public string $title = 'Inquiry';

    public ?string $subtitle = 'Product Request · Internal';

    public string $numberLabel = 'Inquiry No.';

    public array $refs = [
        ['key' => 'inquiry_type', 'label' => 'Kind'],
        ['key' => 'source', 'label' => 'Source'],
        ['key' => 'sales', 'label' => 'Sales'],
        ['key' => 'deadline_quotation', 'label' => 'Quotation Deadline', 'type' => 'date'],
        ['key' => 'product_type', 'label' => 'Product Type'],
    ];

    public string $partyLabel = 'Customer';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

    public string $nb = 'Internal document, without prices. Prices are issued in the Quotation after Pro-Dev and Pro-Cost are done.';

    public string $nameLabel = 'Product / Part';

    public array $extra = [
        ['key' => 'size', 'label' => 'Finished Size (mm)', 'width' => 31],
        ['key' => 'material', 'label' => 'Material', 'width' => 35],
        ['key' => 'color', 'label' => 'Colour', 'width' => 18],
    ];

    public array $receivers = [['Received By', 'Pro-Dev'], ['Received By', 'Pro-Cost']];

    public static function load(int $id, array $opt = []): static
    {
        $q = IqpInquiry::with(['customer', 'tiers', 'parts' => fn ($p) => $p->orderBy('part_seq'), 'parts.materials', 'parts.processes' => fn ($p) => $p->orderBy('step_seq')])->findOrFail($id);
        $c = $q->customer;
        $primary = $q->tiers->firstWhere('is_primary', true) ?? $q->tiers->sortBy('tier_seq')->first();
        $spec = fn ($part) => is_array($part->dieline_spec) ? $part->dieline_spec : (json_decode((string) $part->dieline_spec, true) ?: []);
        $typeCode = $q->parts->map(fn ($p) => $spec($p)['type_code'] ?? null)->filter()->first();
        $num = fn ($v) => Locale::decimal($v, 2);
        $finishing = $q->parts->flatMap(fn ($p) => $p->processes->pluck('process_name'))->filter()->unique()->implode(' · ');
        $alt = $q->tiers->where('id_tier', '<>', $primary?->id_tier)->sortBy('tier_seq')->map(fn ($t) => Locale::number($t->qty, 0))->implode(' · ');
        $tax = trim(($q->tax_scheme ?: '').((float) $q->ppn_percent > 0 && $q->tax_scheme === 'PPN' ? ' '.$num($q->ppn_percent).'%' : ''));
        $own = $q->material_source === 'OWN' ? 'own material' : 'customer material';

        return new self([
            'number' => $q->inquiry_no, 'date' => $q->inquiry_date?->toDateString(), 'status' => $q->sub_state, 'printedBy' => $opt['printedBy'] ?? null,
            'inquiry_type' => $q->inquiry_type, 'source' => $q->source, 'sales' => $q->pic_sales, 'deadline_quotation' => $q->deadline_quotation?->toDateString(),
            'product_type' => $typeCode ? (ProdType::where('type_code', $typeCode)->value('type_name') ?? $typeCode) : null,
            'contact' => implode(' · ', array_filter([$c?->contact_name, $c?->phone])),
            'party' => ['name' => $c?->customer_name ?? ($q->prospect_name ?? ''), 'code' => $c?->customer_code, 'lines' => PrintDocs::address($c?->default_address)],
            'notes' => array_values(array_filter([
                $finishing ? ['Finishing', $finishing] : null,
                $alt ? ['Alternative Qty', $alt.' PCS'] : null,
                ['Tax / Material', trim($tax.' · '.self::t($own), ' ·')],
                $q->sales_note ? ['Sales Notes', $q->sales_note] : null,
            ])),
            'rows' => $q->parts->map(function ($p) use ($q, $primary, $spec, $num) {
                $dims = $spec($p)['dims'] ?? [];
                $size = array_filter([$dims['product_length'] ?? null, $dims['product_width'] ?? null, $dims['product_height'] ?? null]);
                $mat = $p->materials->firstWhere('is_primary', true) ?? $p->materials->first();

                return ['name' => trim($q->product_draft_name.($p->part_name && $p->part_name !== $q->product_draft_name ? ' · '.$p->part_name : '')),
                    'qty' => $primary ? (float) $primary->qty * ((float) $p->qty_per_sku ?: 1) : null, 'unit' => 'PCS',
                    'size' => $size ? implode(' × ', array_map($num, $size)) : ($p->flat_w ? $num($p->flat_w).' × '.$num($p->flat_h).' (flat)' : null),
                    'material' => $mat?->rawmat_name, 'color' => $p->cfg_color];
            })->all(),
        ]);
    }
}
