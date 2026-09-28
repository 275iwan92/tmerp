<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdMarket;
use App\Models\Master\ProdType;
use App\Models\Master\RefFinishingType;
use App\Models\Master\SalesPerson;
use App\Models\Sdi\IqpInquiry;
use App\Services\Sales\QuotationBuilder;

// Data modul Sales untuk layar v2 — SEMUA dari database situs (ddt* di mockup.test, tm2* live):
// worklist, dokumen (inquiryDoc) dan lookup customer/sales/bahan/finishing (2026-09-28).
// Bentuk baris = kolom tabel tujuannya (lihat tooltip Src di layar).
final class Sales
{
    public const STATES = ['DRAFT', 'WAITING_COST', 'SUBMITTED', 'PRICE_CALCULATED', 'PRICE_APPROVED', 'QUOTED', 'PROVING', 'PROVE_APPROVED', 'CONVERTED', 'CANCELLED', 'LOST'];

    public const STATE_TONES = ['DRAFT' => 'neutral', 'WAITING_COST' => 'warn', 'SUBMITTED' => 'warn', 'PRICE_CALCULATED' => 'warn', 'PRICE_APPROVED' => 'info',
        'QUOTED' => 'accent', 'PROVING' => 'accent', 'PROVE_APPROVED' => 'accent', 'CONVERTED' => 'success', 'CANCELLED' => 'danger', 'LOST' => 'danger'];

    public const TYPE_TONES = ['NEW' => 'info', 'RECALC' => 'accent', 'REVISE' => 'warn'];

    // Lookup Customer (tm2mst.cust_customer aktif) — sama dengan customerResults() tmerp.
    public static function customerRows(): array
    {
        return CustCustomer::query()->where('activate', true)->orderBy('customer_name')
            ->get(['id_customer', 'customer_code', 'customer_name'])->toArray();
    }

    // Lookup PIC Sales (tm2mst.sales_person aktif); disimpan sebagai nama (iqp_inquiry.pic_sales) seperti tmerp.
    public static function salesPersons(): array
    {
        return SalesPerson::query()->where('activate', true)->orderBy('sales_type')->orderBy('sales_name')
            ->get(['id_sales_person', 'sales_name', 'sales_type'])->toArray();
    }

    // Bahan (mat_raw aktif & confirmed, materialResults() tmerp) + jenis finishing aktif.
    public static function masters(): array
    {
        static $m;

        return $m ??= [
            'materials' => MatRaw::query()->where('activate', true)->where('stat_confirm', true)->orderBy('material_name')
                ->get(['material_id', 'material_code', 'material_name', 'brand', 'category1', 'gram_weight', 'uom_purchase', 'avg_unitprice', 'last_unitprice'])
                ->map(fn ($r) => ['material_id' => $r->material_id, 'material_code' => $r->material_code, 'material_name' => $r->material_name,
                    'brand' => $r->brand, 'category' => $r->category1, 'gram_weight' => $r->gram_weight, 'uom_purchase' => $r->uom_purchase,
                    'avg_unitprice' => $r->avg_unitprice, 'last_unitprice' => $r->last_unitprice])->all(),
            'finishing_types' => RefFinishingType::query()->where('activate', true)->orderBy('finishing_name')
                ->get(['id_finishing_type', 'finishing_name'])->toArray(),
        ];
    }

    // Tipe produk + dieline_spec + parameter + ukuran pasaran umum, langsung dari tm2mst.prod_type* (2026-09-26;
    // sebelumnya salinan JSON statis yang identik untuk 22 tipe lama). Hanya tipe yang punya dieline (elements).
    public static function dielineTypes(): array
    {
        static $t;

        return $t ??= ProdType::query()->where('activate', true)->orderBy('sort_order')
            ->with(['params' => fn ($q) => $q->where('activate', true)->orderBy('seq'),
                'sizes' => fn ($q) => $q->where('activate', true)->whereNull('market_id')->orderBy('sort_order')->with('values')])
            ->get()->filter(fn ($t) => ! empty($t->dieline_spec['elements'] ?? null))
            ->mapWithKeys(fn ($t) => [$t->type_code => [
                'label' => $t->type_name, 'category' => $t->category, 'spec' => $t->dieline_spec,
                'params' => $t->params->mapWithKeys(fn ($p) => [$p->param_key => ['label' => $p->param_label, 'unit' => $p->unit, 'type' => $p->param_type,
                    'default' => $p->param_type === 'ENUM' ? $p->default_str : ($p->default_value !== null ? (float) $p->default_value : null),
                    'min' => $p->min_value !== null ? (float) $p->min_value : null, 'max' => $p->max_value !== null ? (float) $p->max_value : null, 'options' => $p->option_json]])->all(),
                'sizes' => $t->sizes->map(fn ($z) => ['label' => $z->size_label, 'dims' => self::sizeDims($z)])->values()->all(),
            ]])->all();
    }

    private static function sizeDims($size): array
    {
        return $size->values->mapWithKeys(fn ($v) => [$v->param_key => $v->value_num !== null ? (float) $v->value_num : $v->value_str])->all();
    }

    // Kategori Produk (induk) -> Detail Kategori (Tipe Produk) -> Ukuran Pasaran, dari Lookup Product Market (tm2mst.prod_market).
    public static function productMarkets(): array
    {
        return ProdMarket::query()->where('activate', true)->whereNull('parent_id')->orderBy('sort_order')->orderBy('market_label')
            ->with(['children' => fn ($q) => $q->where('activate', true)->with(['type', 'paperCategory', 'sheetCategory', 'sizes' => fn ($z) => $z->where('activate', true)->with('values')])])
            ->get()->map(fn ($g) => ['code' => $g->market_code, 'label' => $g->market_label,
                'details' => $g->children->filter(fn ($d) => $d->type_code)->map(fn ($d) => [
                    'code' => $d->market_code, 'label' => $d->market_label, 'type_code' => $d->type_code, 'type_name' => $d->type?->type_name,
                    'color' => $d->default_color, 'tiers' => $d->tierList(),
                    // Bahan awal = rantai kategori bahan baku: #1 Paper, #2 Sheet/SF (item persis dipilih kemudian).
                    'materials' => collect([$d->paperCategory, $d->sheetCategory])->filter()->map(fn ($c) => [
                        'label' => ProdMarket::chainLabel($c), 'category' => $c->category_name])->values()->all(),
                    'sizes' => $d->sizes->map(fn ($z) => ['label' => $z->size_label, 'dims' => self::sizeDims($z)])->values()->all(),
                ])->values()->all()])
            ->filter(fn ($g) => $g['details'])->values()->all();
    }

    // Satu dokumen inkuiri lengkap dari database situs (header, tier, part+bahan+finishing, tooling, biaya eksternal) — fillFromInquiry() tmerp.
    public static function inquiryDoc(int $id): ?array
    {
        $i = IqpInquiry::with(['customer:id_customer,customer_name', 'tiers' => fn ($q) => $q->orderBy('tier_seq'),
            'parts' => fn ($q) => $q->orderBy('part_seq'), 'parts.materials' => fn ($q) => $q->orderBy('layer_seq'), 'parts.materials.rawMaterial',
            'parts.finishings' => fn ($q) => $q->orderBy('seq'), 'toolings', 'externalCosts'])->find($id);
        if (! $i) {
            return null;
        }
        $num = fn ($v) => $v === null ? '' : rtrim(rtrim((string) $v, '0'), '.');
        $code = $i->parts->first()?->dieline_spec['type_code'] ?? '';

        return [
            'id' => $i->id_inquiry, 'inquiry_no' => $i->inquiry_no, 'inquiry_type' => $i->inquiry_type, 'sub_state' => $i->sub_state,
            'inquiry_date' => $i->inquiry_date?->toDateString(), 'deadline_quotation' => $i->deadline_quotation?->toDateString(),
            'source' => $i->source, 'pic_sales' => $i->pic_sales, 'customer_id' => $i->customer_id, 'is_prospect' => (bool) $i->is_prospect,
            'prospect_name' => $i->prospect_name, 'customer' => $i->is_prospect ? $i->prospect_name : ($i->customer?->customer_name ?? '—'),
            'product_draft_name' => $i->product_draft_name, 'sales_note' => $i->sales_note ?? '', 'material_source' => $i->material_source,
            'cust_material_spec' => $i->cust_material_spec ?? '', 'cust_material_eta' => $i->cust_material_eta?->toDateString(),
            'stat_confirm' => (bool) $i->stat_confirm, 'stat_cancel' => (bool) $i->stat_cancel, 'cancel_reason' => $i->cancel_reason,
            'is_approved' => (bool) $i->is_approved, 'revision_no' => (int) $i->revision_no, 'design_number' => $i->design_number,
            'revise_scope' => $i->reviseScopeArray(), 'revise_reason' => $i->revise_reason ?? '', 'parent_id' => $i->parent_product_id,
            'type_code' => isset(self::dielineTypes()[$code]) ? $code : '',
            'qty' => (float) ($i->tiers->firstWhere('is_primary', true)?->qty ?? 0),
            'tiers' => $i->tiers->map(fn ($t) => ['id' => $t->id_tier, 'qty' => $num($t->qty), 'is_primary' => (bool) $t->is_primary])->values()->all(),
            'parts' => $i->parts->map(fn ($p) => [
                'id' => $p->id_part, 'part_name' => $p->part_name, 'part_role' => '', 'part_type' => $p->part_type ?? 'DC',
                'qty_per_sku' => $num($p->qty_per_sku) ?: '1', 'pack_qty' => $p->pack_qty ?? '', 'dims' => $p->dieline_spec['dims'] ?? [],
                'flat_w' => $num($p->flat_w), 'flat_h' => $num($p->flat_h), 'flat_area' => $num($p->flat_area),
                'cfg_color' => $p->cfg_color ?? '4/4', 'cfg_ink' => $p->cfg_ink ?? '',
                'artwork_url' => $p->artwork_path ? asset('storage/'.$p->artwork_path) : null, 'artwork_name' => $p->artwork_original_filename,
                'materials' => $p->materials->map(fn ($m) => ['id' => $m->id_part_material, 'material' => $m->rawmat_id ?: '',
                    'material_label' => $m->rawmat_name ?? $m->rawMaterial?->material_name ?? '', 'brand' => $m->rawMaterial?->brand ?? '',
                    'category' => $m->rawMaterial?->category1 ?? '', 'is_primary' => (bool) $m->is_primary])->values()->all(),
                'finishings' => $p->finishings->map(fn ($f) => ['finishing_type_id' => $f->finishing_type_id, 'attribute' => $f->attribute ?? ''])->values()->all(),
                'sent' => (bool) $p->prod_part_id, 'prod_part_id' => $p->prod_part_id,
            ])->values()->all(),
            'toolings' => $i->toolings->map(fn ($t) => ['id' => $t->id_tooling, 'tooling_kind' => $t->tooling_kind, 'tooling_status' => $t->tooling_status,
                'is_new_charge' => (bool) $t->is_new_charge, 'cost' => $num($t->cost), 'remark' => $t->remark ?? ''])->values()->all(),
            'external_costs' => $i->externalCosts->map(fn ($c) => ['id' => $c->id_external_cost, 'description' => $c->description ?? '', 'cost_kind' => $c->cost_kind,
                'amount' => $num($c->amount), 'tax_treatment' => $c->tax_treatment])->values()->all(),
        ];
    }

    // Inkuiri asal dari dokumen REVISE/RECALC (tmerp: parentInquiry() = parent_product_id).
    public static function parentDoc(int $id): ?array
    {
        $parent = IqpInquiry::whereKey($id)->value('parent_product_id');

        return $parent ? self::inquiryDoc((int) $parent) : null;
    }

    // Worklist Inquiry dari database situs (mockup.test = ddtsdi). prodev/procost/ready =
    // bendera untuk tombol & kotak statistik (keputusan user 2026-09-28):
    // prodev = ada part yang sudah punya prod_part (Pro-Dev), procost = sudah ada costing,
    // ready = sama persis dgn jendela "Choose inquiry" Quotation (costing published, belum di-quote).
    public static function inquiries(): array
    {
        static $rows;

        if ($rows !== null) {
            return $rows;
        }

        $ready = QuotationBuilder::availableInquiries()->pluck('id_inquiry')->flip();

        return $rows = IqpInquiry::query()
            ->with(['customer:id_customer,customer_code,customer_name', 'tiers' => fn ($q) => $q->where('is_primary', true),
                'parts' => fn ($q) => $q->orderBy('part_seq')])
            ->withExists(['costing as procost', 'parts as prodev' => fn ($q) => $q->whereNotNull('prod_part_id')])
            ->orderByDesc('id_inquiry')
            ->limit(500)
            ->get()
            ->map(fn (IqpInquiry $i) => [
                'id' => $i->id_inquiry,
                'inquiry_no' => $i->inquiry_no,
                'inquiry_date' => $i->inquiry_date?->toDateString(),
                'customer' => $i->is_prospect ? $i->prospect_name : ($i->customer?->customer_name ?? '—'),
                'customer_id' => $i->customer_id,
                'is_prospect' => (bool) $i->is_prospect,
                'product_draft_name' => $i->product_draft_name ?: '(tanpa nama produk)',
                'part_type' => $i->parts->first()?->dieline_spec['type_code'] ?? $i->parts->first()?->part_type ?? '—',
                'qty' => (float) ($i->tiers->first()?->qty ?? 0),
                'sub_state' => $i->sub_state,
                'inquiry_type' => $i->inquiry_type,
                'stat_confirm' => (bool) $i->stat_confirm,
                'is_approved' => (bool) $i->is_approved,
                'pic_sales' => $i->pic_sales,
                'deadline_quotation' => $i->deadline_quotation?->toDateString(),
                'prodev' => (bool) $i->prodev,
                'procost' => (bool) $i->procost,
                'ready' => $ready->has($i->id_inquiry),
            ])->all();
    }
}
