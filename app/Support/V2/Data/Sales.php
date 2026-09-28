<?php

namespace App\Support\V2\Data;

use App\Models\Master\ProdMarket;
use App\Models\Master\ProdType;
use App\Models\Sdi\IqpInquiry;
use App\Services\Sales\QuotationBuilder;

// Data modul Sales untuk layar v2. Worklist Inquiry sudah dari database situs (2026-09-28);
// isi part dokumen (inquiryDoc) masih disusun dari baris worklist + master.
// Bentuk baris = kolom tabel tujuannya (lihat tooltip Src di layar).
final class Sales
{
    public const STATES = ['DRAFT', 'WAITING_COST', 'SUBMITTED', 'PRICE_CALCULATED', 'PRICE_APPROVED', 'QUOTED', 'PROVING', 'PROVE_APPROVED', 'CONVERTED', 'CANCELLED', 'LOST'];

    public const STATE_TONES = ['DRAFT' => 'neutral', 'WAITING_COST' => 'warn', 'SUBMITTED' => 'warn', 'PRICE_CALCULATED' => 'warn', 'PRICE_APPROVED' => 'info',
        'QUOTED' => 'accent', 'PROVING' => 'accent', 'PROVE_APPROVED' => 'accent', 'CONVERTED' => 'success', 'CANCELLED' => 'danger', 'LOST' => 'danger'];

    public const TYPE_TONES = ['NEW' => 'info', 'RECALC' => 'accent', 'REVISE' => 'warn'];

    public static function customers(): array
    {
        return ['CV. Sinar Kemasan', 'PT. Boga Rasa Nusantara', 'Toko Manis Jaya', 'PT. Kosmetika Indah', 'PT. Sari Roti Jatim', 'UD. Kopi Arjuno', 'PT. Farma Sehat', 'CV. Batik Tulis Madura'];
    }

    // Baris lookup customer (bentuk tm2mst.cust_customer).
    public static function customerRows(): array
    {
        return array_map(fn ($n, $i) => ['id_customer' => 101 + $i, 'customer_code' => sprintf('C%04d', 101 + $i), 'customer_name' => $n], self::customers(), array_keys(self::customers()));
    }

    // Bentuk tm2mst.sales_person (nama fiktif).
    public static function salesPersons(): array
    {
        return [['id_sales_person' => 1, 'sales_name' => 'Andi Pratama', 'sales_type' => 'INTERNAL'], ['id_sales_person' => 2, 'sales_name' => 'Citra Rahma', 'sales_type' => 'INTERNAL'],
            ['id_sales_person' => 3, 'sales_name' => 'Maya Lestari', 'sales_type' => 'INTERNAL'], ['id_sales_person' => 4, 'sales_name' => 'Budi Agen Jatim', 'sales_type' => 'EXTERNAL']];
    }

    // Ekspor baca-saja tm2mst (bahan kertas, jenis finishing, tipe multi-part): resources/data/sales_masters.json.
    public static function masters(): array
    {
        static $m;

        return $m ??= json_decode(file_get_contents(resource_path('data/v2/sales_masters.json')), true);
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

    // Satu dokumen inkuiri lengkap (header + tier + part) untuk dibuka dari worklist.
    public static function inquiryDoc(int $id): ?array
    {
        $row = collect(self::inquiries())->firstWhere('id', $id);
        if (! $row) {
            return null;
        }
        $types = self::dielineTypes();
        $code = isset($types[$row['part_type']]) ? $row['part_type'] : 'CARD_BOX';
        $mat = self::masters()['materials'][$id % 60];
        $multi = in_array($code, ['BOX_WITH_LID', 'ROUND_BOX', 'SHALLOW_BOX'], true);
        $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', $row['product_draft_name']));
        $part = fn (string $role) => ['part_name' => $slug.'-'.$role, 'part_role' => $role, 'part_type' => 'DC', 'qty_per_sku' => 1, 'pack_qty' => 400,
            'dims' => array_map(fn ($p) => $p['default'], $types[$code]['params']), 'cfg_color' => '4/4', 'cfg_ink' => '',
            'materials' => [['material' => $mat['material_id'], 'material_label' => $mat['material_name'], 'brand' => $mat['brand'], 'category' => $mat['category'], 'is_primary' => true]],
            'finishings' => $id % 2 ? [['finishing_type_id' => 1, 'attribute' => '']] : [], 'sent' => in_array($row['sub_state'], ['PRICE_APPROVED', 'QUOTED', 'PROVING', 'PROVE_APPROVED', 'CONVERTED'], true)];
        $cust = collect(self::customerRows())->firstWhere('customer_name', $row['customer']);

        return $row + [
            'source' => ['EMAIL', 'WA', 'PHONE', 'VISIT'][$id % 4], 'customer_id' => $cust['id_customer'] ?? null, 'sales_note' => '', 'material_source' => 'OWN',
            'type_code' => $code, 'stat_cancel' => $row['sub_state'] === 'CANCELLED', 'revision_no' => $id % 3, 'design_number' => sprintf('DS%06d', 3400 + $id),
            'revise_scope' => $row['inquiry_type'] === 'REVISE' ? ['MATERIAL', 'COLOR'] : [], 'revise_reason' => $row['inquiry_type'] === 'REVISE' ? 'Tambah logo Halal (spot ink) — permintaan customer' : '',
            'tiers' => [['qty' => $row['qty'], 'is_primary' => true], ['qty' => $row['qty'] * 2, 'is_primary' => false]],
            'parts' => $multi ? [$part('body'), $part('lid')] : [$part('monoform')],
            'toolings' => [], 'external_costs' => [],
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
