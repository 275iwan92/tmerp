<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdMarket;
use App\Models\Master\ProdType;
use App\Models\Master\RefFinishingType;
use App\Models\Master\SalesPerson;
use App\Models\Sdi\IqpInquiry;
use App\Services\Sales\InquiryStage;
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

    // Bahan Inquiry (user 2026-09-29): Sales pilih Jenis + Gramatur/Spec saja dari hirarki mat_category
    // (sama dengan Lookup Product Market), tanpa merek & ukuran. Slot 0 = Paper, slot 1 = Sheet/SF.
    // gram = [id Jenis => [[id, nama], ...]] -- pasangan (bukan objek) supaya urutan tetap di JS.
    public static function materialChains(): array
    {
        static $c;

        return $c ??= array_map(function ($root) {
            $rootId = MatCategory::roots()->where('category_name', $root)->value('id_category');
            $jenis = MatCategory::where('parent_id', $rootId)->where('level', 2)->where('activate', true)->get(['id_category', 'category_name'])
                ->sortBy('category_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
            $gram = MatCategory::whereIn('parent_id', $jenis->pluck('id_category'))->where('level', 3)->where('activate', true)
                ->get(['id_category', 'parent_id', 'category_name'])->sortBy('category_name', SORT_NATURAL | SORT_FLAG_CASE);

            return ['root' => $root, 'label' => $root === 'Paper' ? 'Paper' : 'Sheet / SF',
                'jenis' => $jenis->map(fn ($j) => [$j->id_category, $j->category_name])->all(),
                'gram' => $gram->groupBy('parent_id')->map(fn ($g) => $g->map(fn ($x) => [$x->id_category, trim($x->category_name)])->values()->all())->all()];
        }, ['Paper', 'Sheet']);
    }

    // id kategori tersimpan (node terdalam) -> ['chain' => 0 Paper | 1 Sheet, 'jenis' => id level 2, 'gramatur' => id level 3].
    public static function materialSlot(?int $categoryId): array
    {
        static $parent;
        $parent ??= MatCategory::whereIn('level', [2, 3])->pluck('parent_id', 'id_category')->all();
        $level3 = $categoryId && isset($parent[$categoryId]) && isset($parent[$parent[$categoryId]]);
        $known = $categoryId && isset($parent[$categoryId]);
        $jenis = $known ? ($level3 ? $parent[$categoryId] : $categoryId) : '';
        $sheet = $jenis !== '' && in_array($jenis, array_column(self::materialChains()[1]['jenis'], 0));

        return ['chain' => $sheet ? 1 : 0, 'jenis' => $jenis, 'gramatur' => $level3 ? $categoryId : ''];
    }

    // Baris bahan part untuk layar: [0] = baris Paper pertama, [1] = baris Sheet/SF pertama, sisanya lapis tambahan (urut layer_seq).
    // Baris lama tanpa kategori (dulu pilih mat_raw) mengisi baris bawaan yang masih kosong, lalu jadi baris tambahan.
    public static function materialRows($materials): array
    {
        $fixed = [0 => null, 1 => null];
        $extra = [];
        foreach ($materials->sortBy('layer_seq') as $m) {
            $r = ['id' => $m->id_part_material, 'label' => $m->rawmat_name ?? ''] + self::materialSlot($m->category_id);
            $slot = $r['jenis'] !== '' ? $r['chain'] : (array_search(null, $fixed, true) === false ? null : array_search(null, $fixed, true));
            if ($slot !== null && $fixed[$slot] === null) {
                $fixed[$slot] = ['chain' => $slot] + $r;
            } else {
                $extra[] = $r;
            }
        }

        return array_merge([$fixed[0] ?? ['chain' => 0], $fixed[1] ?? ['chain' => 1]], $extra);
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
            ->with(['children' => fn ($q) => $q->where('activate', true)->with(['type', 'sizes' => fn ($z) => $z->where('activate', true)->with('values')])])
            ->get()->map(fn ($g) => ['code' => $g->market_code, 'label' => $g->market_label,
                'details' => $g->children->filter(fn ($d) => $d->type_code)->map(fn ($d) => [
                    'code' => $d->market_code, 'label' => $d->market_label, 'type_code' => $d->type_code, 'type_name' => $d->type?->type_name,
                    'color' => $d->default_color, 'tiers' => $d->tierList(),
                    // Bahan awal slot #1 Paper, #2 Sheet/SF = Jenis + Gramatur dari rantai kategori bahan baku.
                    'materials' => [self::materialSlot($d->paper_category_id), self::materialSlot($d->sheet_category_id)],
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
        $code = $i->parts->first(fn ($p) => $p->part_kind !== 'ASSEMBLY')?->dieline_spec['type_code'] ?? '';

        return [
            'id' => $i->id_inquiry, 'inquiry_no' => $i->inquiry_no, 'inquiry_type' => $i->inquiry_type, 'sub_state' => $i->sub_state,
            'inquiry_date' => $i->inquiry_date?->toDateString(), 'deadline_quotation' => $i->deadline_quotation?->toDateString(),
            'source' => $i->source, 'pic_sales' => $i->pic_sales, 'customer_id' => $i->customer_id, 'is_prospect' => (bool) $i->is_prospect,
            'prospect_name' => $i->prospect_name, 'customer' => $i->is_prospect ? $i->prospect_name : ($i->customer?->customer_name ?? '—'),
            'product_draft_name' => $i->product_draft_name, 'sales_note' => $i->sales_note ?? '', 'material_source' => $i->material_source,
            'cust_material_spec' => $i->cust_material_spec ?? '', 'cust_material_eta' => $i->cust_material_eta?->toDateString(),
            'stat_confirm_sales' => (bool) $i->stat_confirm_sales, 'stat_cancel' => (bool) $i->stat_cancel, 'cancel_reason' => $i->cancel_reason,
            'is_approved' => (bool) $i->is_approved, 'revision_no' => (int) $i->revision_no, 'design_number' => $i->design_number,
            'revise_scope' => $i->reviseScopeArray(), 'revise_reason' => $i->revise_reason ?? '', 'parent_id' => $i->parent_inquiry_id, 'product_id' => $i->product_id,
            'type_code' => isset(self::dielineTypes()[$code]) ? $code : '',
            'qty' => (float) ($i->tiers->firstWhere('is_primary', true)?->qty ?? 0),
            'tiers' => $i->tiers->map(fn ($t) => ['id' => $t->id_tier, 'qty' => $num($t->qty), 'is_primary' => (bool) $t->is_primary])->values()->all(),
            'parts' => $i->parts->where('part_kind', '<>', 'ASSEMBLY')->map(fn ($p) => [
                'id' => $p->id_part, 'part_name' => $p->part_name, 'part_role' => '', 'part_type' => $p->part_type ?? 'DC',
                'qty_per_sku' => $num($p->qty_per_sku) ?: '1', 'pack_qty' => $p->pack_qty ?? '', 'dims' => $p->dieline_spec['dims'] ?? [],
                'flat_w' => $num($p->flat_w), 'flat_h' => $num($p->flat_h), 'flat_area' => $num($p->flat_area),
                'cfg_color' => $p->cfg_color ?? '4/4', 'cfg_ink' => $p->cfg_ink ?? '',
                'artwork_url' => $p->artwork_path ? asset('storage/'.$p->artwork_path) : null, 'artwork_name' => $p->artwork_original_filename,
                'artwork_inner_url' => $p->artwork_inner_path ? asset('storage/'.$p->artwork_inner_path) : null, 'artwork_inner_name' => $p->artwork_inner_original_filename,
                'materials' => $p->materials->map(fn ($m) => ['id' => $m->id_part_material, 'material' => $m->rawmat_id ?: '',
                    'material_label' => $m->rawmat_name ?? $m->rawMaterial?->material_name ?? '', 'brand' => $m->rawMaterial?->brand ?? '',
                    'category' => $m->rawMaterial?->category1 ?? '', 'is_primary' => (bool) $m->is_primary])->values()->all(),
                'material_rows' => self::materialRows($p->materials),
                'finishings' => $p->finishings->map(fn ($f) => ['finishing_type_id' => $f->finishing_type_id, 'attribute' => $f->attribute ?? ''])->values()->all(),
                'sent' => (bool) $p->prod_part_id, 'prod_part_id' => $p->prod_part_id,
            ])->values()->all(),
            'toolings' => $i->toolings->map(fn ($t) => ['id' => $t->id_tooling, 'tooling_kind' => $t->tooling_kind, 'tooling_status' => $t->tooling_status,
                'is_new_charge' => (bool) $t->is_new_charge, 'cost' => $num($t->cost), 'remark' => $t->remark ?? ''])->values()->all(),
            'external_costs' => $i->externalCosts->map(fn ($c) => ['id' => $c->id_external_cost, 'description' => $c->description ?? '', 'cost_kind' => $c->cost_kind,
                'amount' => $num($c->amount), 'tax_treatment' => $c->tax_treatment])->values()->all(),
            'market' => self::marketOf($i->parts->first(fn ($p) => $p->part_kind !== 'ASSEMBLY')?->dieline_spec['dims'] ?? []),
        ];
    }

    // Kategori/Detail/Ukuran tidak disimpan di inkuiri (hanya pemandu isian) -> untuk dokumen tersimpan ditebak dari
    // ukuran part yang sama persis dengan satu Ukuran Pasaran. Tidak ada yang cocok = dibiarkan kosong.
    public static function marketOf(array $dims): ?array
    {
        $want = array_filter(array_intersect_key($dims, array_flip(['product_length', 'product_width', 'product_height'])), fn ($v) => (float) $v > 0);
        if (! $want) {
            return null;
        }
        foreach (self::productMarkets() as $g) {
            foreach ($g['details'] as $d) {
                foreach ($d['sizes'] as $k => $z) {
                    if (collect($want)->every(fn ($v, $key) => isset($z['dims'][$key]) && abs((float) $z['dims'][$key] - (float) $v) < 0.01)) {
                        return ['group' => $g['code'], 'detail' => $d['code'], 'size' => $k];
                    }
                }
            }
        }

        return null;
    }

    // Inkuiri asal dari dokumen REVISE/RECALC (parentInquiry()).
    public static function parentDoc(int $id): ?array
    {
        $parent = IqpInquiry::whereKey($id)->value('parent_inquiry_id');

        return $parent ? self::inquiryDoc((int) $parent) : null;
    }

    // Worklist Inquiry dari database situs (mockup.test = ddtsdi). Satu tabel, tiga tanda (rulebook A.20):
    // prodev / procost = inquiry sedang di meja bagian itu (dihitung dari stat_confirm_*), ready = sama persis dgn jendela
    // "Choose inquiry" Quotation (costing published, belum di-quote). items = baris anak grid (part + status ProDev-nya).
    public static function inquiries(): array
    {
        static $rows;

        if ($rows !== null) {
            return $rows;
        }

        $ready = QuotationBuilder::availableInquiries()->pluck('id_inquiry')->flip();
        $list = IqpInquiry::query()
            ->with(['customer:id_customer,customer_code,customer_name', 'tiers' => fn ($q) => $q->where('is_primary', true),
                'parts' => fn ($q) => $q->orderBy('part_seq'), 'costing:id_costing,inquiry_id,doc_status', 'parentInquiry:id_inquiry,inquiry_no'])
            ->orderByDesc('id_inquiry')
            ->limit(500)
            ->get();
        $partStatus = InquiryStage::partStatuses($list->modelKeys())->keyBy('id_part');

        return $rows = $list->map(function (IqpInquiry $i) use ($ready, $partStatus) {
            $dead = $i->stat_cancel || in_array($i->sub_state, ['CANCELLED', 'LOST'], true);
            $viaProdev = in_array($i->inquiry_type, InquiryStage::PRODEV_TYPES, true);
            $atProdev = ! $dead && $viaProdev && $i->stat_confirm_sales && ! $i->stat_confirm_prodev;
            $atProcost = ! $dead && $i->stat_confirm_sales && ! $i->stat_confirm_procost && (! $viaProdev || $i->stat_confirm_prodev);

            return [
                'id' => $i->id_inquiry,
                'inquiry_no' => $i->inquiry_no,
                'inquiry_date' => $i->inquiry_date?->toDateString(),
                'customer' => $i->is_prospect ? $i->prospect_name : ($i->customer?->customer_name ?? '—'),
                'customer_id' => $i->customer_id,
                'is_prospect' => (bool) $i->is_prospect,
                'product_draft_name' => $i->product_draft_name ?: t('(no product name)'),
                'part_type' => $i->parts->first()?->dieline_spec['type_code'] ?? $i->parts->first()?->part_type ?? '—',
                'qty' => (float) ($i->tiers->first()?->qty ?? 0),
                'sub_state' => $i->sub_state,
                'inquiry_type' => $i->inquiry_type,
                'stat_confirm_sales' => (bool) $i->stat_confirm_sales,
                'stat_confirm_prodev' => (bool) $i->stat_confirm_prodev,
                'stat_confirm_procost' => (bool) $i->stat_confirm_procost,
                'sales_at' => $i->confirm_sales_at?->toDateString(),
                'prodev_at' => $i->confirm_prodev_at?->toDateString(),
                'procost_at' => $i->confirm_procost_at?->toDateString(),
                'dead' => $dead,
                'is_approved' => (bool) $i->is_approved,
                'pic_sales' => $i->pic_sales,
                'deadline_quotation' => $i->deadline_quotation?->toDateString(),
                'costing' => $i->costing?->doc_status ?? 'NOT STARTED',
                'scope' => $i->revise_scope ? str_replace(',', ', ', $i->revise_scope) : null,
                'source' => $i->parentInquiry?->inquiry_no,
                'prodev' => $atProdev,
                'procost' => $atProcost,
                'ready' => $ready->has($i->id_inquiry),
                'stage' => match (true) {
                    $dead => $i->sub_state,
                    ! $i->stat_confirm_sales => 'Draft',
                    $atProdev => 'Pro-Dev',
                    $atProcost => 'Pro-Cost',
                    $ready->has($i->id_inquiry) => 'Ready to Quote',
                    default => $i->sub_state,
                },
                'items' => $i->parts->map(function ($p) use ($partStatus) {
                    $d = $p->dieline_spec['dims'] ?? [];
                    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');

                    return ['part' => $p->part_name, 'kind' => $p->part_kind === 'ASSEMBLY' ? 'Assembly' : ($p->part_type ?: '—'),
                        'type' => $p->dieline_spec['type_code'] ?? '—',
                        'size' => isset($d['product_length']) ? $n($d['product_length']).' × '.$n($d['product_width'] ?? 0).' × '.$n($d['product_height'] ?? 0) : '—',
                        'flat' => $p->flat_w ? $n($p->flat_w).' × '.$n($p->flat_h) : '—',
                        'color' => $p->cfg_color ?: '—',
                        'prodev' => $p->part_kind === 'ASSEMBLY' ? '—' : (['NOT_STARTED' => 'Not started', 'DRAFT' => 'Draft', 'CONFIRMED' => 'Confirmed'][$partStatus[$p->id_part]->status ?? 'NOT_STARTED'])];
                })->all(),
            ];
        })->all();
    }

    public const STAGE_TONES = ['Draft' => 'neutral', 'Pro-Dev' => 'violet', 'Pro-Cost' => 'warn', 'Ready to Quote' => 'success'] + self::STATE_TONES;

    // Baris anak grid Inquiry / Pro-Dev / Pro-Cost (rulebook C.28): nama product di judul, part di tabel (7 kolom).
    public const PART_DETAIL = ['key' => 'items', 'title' => 'Product: {product_draft_name}', 'columns' => [
        ['key' => 'part', 'label' => 'Part', 'strong' => true],
        ['key' => 'kind', 'label' => 'Part Type'],
        ['key' => 'type', 'label' => 'Product Type'],
        ['key' => 'size', 'label' => 'Size (mm)'],
        ['key' => 'flat', 'label' => 'Flat Size (mm)'],
        ['key' => 'color', 'label' => 'Colors'],
        ['key' => 'prodev', 'label' => 'Pro-Dev'],
    ]];

    // Saringan satu meja: $type = NEW | REVISE | RECALC | null (semua); $desk = SALES | PRODEV | PROCOST.
    // PRODEV  = sudah Confirm Sales (To Do: belum Confirm ProDev);  PROCOST = sudah lewat ProDev (RECALC: langsung dari Sales).
    public static function desk(?string $type, string $desk): array
    {
        return array_values(array_filter(self::inquiries(), function ($r) use ($type, $desk) {
            if ($type && $r['inquiry_type'] !== $type) {
                return false;
            }
            $viaProdev = in_array($r['inquiry_type'], InquiryStage::PRODEV_TYPES, true);

            return match ($desk) {
                'PRODEV' => $viaProdev && $r['stat_confirm_sales'] && ! $r['dead'],
                'PROCOST' => $r['stat_confirm_sales'] && ! $r['dead'] && (! $viaProdev || $r['stat_confirm_prodev']),
                default => true,
            };
        }));
    }
}
