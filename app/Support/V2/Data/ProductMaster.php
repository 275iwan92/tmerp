<?php

namespace App\Support\V2\Data;

use App\Models\Master\MatCategory;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\ProdProductAcc;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sdi\QuoQuotationItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Master Product untuk layar v2 (Master Product, Revisi, Rekalkulasi, ACC Digital) -- docs/flow_product_master.md.
// Semua dari database situs. Sisi Sales: harga JUAL saja; HPP, margin, harga beli bahan, ukuran plano tidak dibaca di sini.
final class ProductMaster
{
    // Product yang punya part (lahir dari Inquiry/ProDev) -- 14 ribu product legacy tanpa part tidak ikut dipilih.
    public static function options(): array
    {
        $rows = ProdProduct::query()->whereHas('parts')->with('customer:id_customer,customer_name')
            ->orderBy('product_name')->get(['id_product', 'product_code', 'product_name', 'design_no', 'customer_id', 'revision_no', 'card_status']);

        return $rows->mapWithKeys(fn ($p) => [$p->id_product => trim($p->product_name.' · '.($p->design_no ?: $p->product_code)
            .($p->revision_no ? ' · Rev.'.$p->revision_no : '').' · '.($p->customer?->customer_name ?? '—').($p->card_status === 'ACTIVE' ? '' : ' (DRAFT)'))])->all();
    }

    // Baris Grid for Master: foto = artwork luar komponen pertama; status ACC Digital dari prod_product_acc.
    public static function rows(): array
    {
        $products = ProdProduct::query()->whereHas('parts')->with('customer:id_customer,customer_name')->withCount('parts')->orderByDesc('id_product')
            ->get(['id_product', 'product_code', 'product_name', 'design_no', 'customer_id', 'revision_no', 'card_status', 'parent_product_id', 'activate', 'updated_at']);
        $ids = $products->pluck('id_product');
        $art = DB::connection('tm2mst')->table('prod_part_artwork as a')->join('prod_part as p', 'p.id_part', '=', 'a.part_id')
            ->whereIn('p.product_id', $ids)->where('a.activate', true)->where('a.side', 'OUTER')->orderBy('p.id_part')->get(['p.product_id', 'a.artwork_path'])
            ->unique('product_id')->pluck('artwork_path', 'product_id');
        $acc = ProdProductAcc::whereIn('product_id', $ids)->get(['product_id', 'acc_type'])->groupBy('product_id');
        $join = DB::connection('tm2mst')->table('prod_part as p')->join('mat_join_type as j', 'j.id_join_type', '=', 'p.join_type_id')
            ->whereIn('p.product_id', $ids)->pluck('j.label', 'p.product_id');

        return $products->map(fn ($p) => [
            'id' => $p->id_product, 'photo' => isset($art[$p->id_product]) ? asset('storage/'.$art[$p->id_product]) : null,
            'name' => $p->product_name, 'code' => $p->design_no ?: $p->product_code, 'customer' => $p->customer?->customer_name ?? '—',
            'parts' => $p->parts_count, 'join' => $join[$p->id_product] ?? '—', 'status' => $p->card_status ?: 'DRAFT',
            'revision' => $p->revision_no ? 'Rev.'.$p->revision_no : t('Parent'),
            'acc_digital' => ($acc[$p->id_product] ?? collect())->contains('acc_type', 'DIGITAL') ? t('ON FILE', 'badge') : t('NOT YET', 'badge'),
            'active' => (bool) $p->activate, 'updated' => $p->updated_at?->toDateString(),
        ])->all();
    }

    public static function doc(int $id): ?array
    {
        $p = ProdProduct::with(['customer:id_customer,customer_name,customer_code', 'parentProduct:id_product,product_name,design_no,revision_no',
            'revisions:id_product,parent_product_id,product_name,design_no,revision_no,card_status', 'accs'])->find($id);
        if (! $p) {
            return null;
        }
        $parts = ProdPart::where('product_id', $id)->with('joinType')->orderBy('id_part')->get();

        return [
            'id' => $p->id_product, 'code' => $p->product_code, 'name' => $p->product_name, 'design_no' => $p->design_no,
            'drawing_by' => $p->drawing_by, 'design_date' => $p->design_date, 'status' => $p->card_status, 'revision_no' => (int) $p->revision_no,
            'customer' => $p->customer?->customer_name ?? '—', 'customer_id' => $p->customer_id,
            'dims' => array_map(fn ($v) => $v === null ? null : (float) $v, ['p' => $p->cm_length, 'l' => $p->cm_width, 't' => $p->cm_height]),
            'source_inquiry_id' => $p->source_inquiry_id,
            'parent' => $p->parentProduct ? ['id' => $p->parentProduct->id_product, 'label' => $p->parentProduct->product_name.' · '.$p->parentProduct->design_no] : null,
            'revisions' => $p->revisions->map(fn ($r) => ['id' => $r->id_product, 'label' => $r->product_name.' · '.($r->design_no ?: 'Rev.'.$r->revision_no), 'status' => $r->card_status])->all(),
            'join' => $parts->firstWhere('part_kind', 'ASSEMBLY')?->joinType?->label,
            'tree' => self::tree($parts, null),
            'components' => $parts->where('part_kind', 'COMPONENT')->map(fn ($x) => self::part($x))->values()->all(),
            'accs' => $p->accs->map(fn (ProdProductAcc $a) => ['type' => $a->acc_type, 'at' => \Devlib\Locale::dt($a->acc_at), 'by' => $a->approved_by,
                'channel' => ProdProductAcc::CHANNELS[$a->channel] ?? $a->channel, 'note' => $a->note,
                'evidence' => $a->evidence_path ? asset('storage/'.$a->evidence_path) : null, 'evidence_name' => $a->evidence_filename])->all(),
        ];
    }

    private static function tree(Collection $parts, ?int $parent): array
    {
        return $parts->where('parent_part_id', $parent)->map(fn ($x) => self::part($x) + ['children' => self::tree($parts, $x->id_part)])->values()->all();
    }

    public static function part(ProdPart $x): array
    {
        $mst = DB::connection('tm2mst');
        $art = $mst->table('prod_part_artwork')->where('part_id', $x->id_part)->where('activate', true)->pluck('artwork_path', 'side');
        $spec = $x->dieline_spec ?? [];
        $chain = fn ($cat) => $cat ? implode(' › ', array_map(fn ($c) => $c->category_name, MatCategory::find($cat)?->ancestorChain() ?? [])) : null;

        return [
            'id' => $x->id_part, 'name' => $x->part_name, 'kind' => $x->part_kind, 'code' => $x->part_code, 'part_type' => $x->part_type,
            'join' => $x->joinType?->label, 'join_code' => $x->joinType?->code, 'qty_in_parent' => (float) $x->qty_in_parent,
            'color' => $x->cfg_color, 'ink' => $x->cfg_ink, 'pack_qty' => $x->pack_qty,
            'flat' => $x->flat_w ? rtrim(rtrim((string) $x->flat_w, '0'), '.').' × '.rtrim(rtrim((string) $x->flat_h, '0'), '.').' mm' : null,
            'type_code' => $spec['type_code'] ?? null, 'dims' => $spec['dims'] ?? [], 'has_spec' => ! empty($spec['elements']),
            'artwork' => isset($art['OUTER']) ? asset('storage/'.$art['OUTER']) : null,
            'artwork_inner' => isset($art['INNER']) ? asset('storage/'.$art['INNER']) : null,
            'materials' => $mst->table('prod_part_material as m')->leftJoin('mat_raw as r', 'r.material_id', '=', 'm.material_id')->where('m.part_id', $x->id_part)
                ->orderBy('m.layer_seq')->get(['m.layer_seq', 'm.role', 'm.category_id', 'm.gram_weight', 'm.note', 'r.material_name', 'r.brand'])
                ->map(fn ($m) => ['seq' => $m->layer_seq, 'role' => $m->role, 'jenis' => $chain($m->category_id) ?? $m->material_name ?? $m->note, 'brand' => $m->brand])->all(),
            'inks' => $mst->table('prod_part_ink')->where('part_id', $x->id_part)->orderBy('pass_seq')->orderBy('unit_no')->get(['pass_seq', 'unit_no', 'ink_name'])
                ->map(fn ($k) => ['pass' => $k->pass_seq, 'unit' => $k->unit_no, 'ink' => $k->ink_name])->all(),
            'bop' => $mst->table('prod_part_process as s')->leftJoin('prod_process as p', 'p.id_process', '=', 's.process_id')->leftJoin('prod_machine as mc', 'mc.machine_id', '=', 's.machine_id')
                ->where('s.part_id', $x->id_part)->orderBy('s.step_seq')->get(['s.process_name', 'p.process_name as master_name', 'mc.machine_name', 's.note'])
                ->map(fn ($s) => ['name' => $s->process_name ?: $s->master_name, 'machine' => $s->machine_name, 'note' => $s->note])->all(),
            'finishings' => $mst->table('prod_part_finishing as f')->leftJoin('ref_finishing_type as t', 't.id_finishing_type', '=', 'f.finishing_type_id')
                ->where('f.part_id', $x->id_part)->orderBy('f.seq')->get(['t.finishing_name', 'f.attribute'])
                ->map(fn ($f) => trim($f->finishing_name.' '.$f->attribute))->all(),
        ];
    }

    // Riwayat order & harga JUAL: tiap Quotation yang memuat inquiry product ini (+ CO tanpa Quotation), terbaru di atas.
    // Hasil = WIN (CO lahir) / LOST / Terbuka; pengiriman dari jadwal CPDEL.
    public static function orders(int $productId): array
    {
        $inquiries = IqpInquiry::where('product_id', $productId)->get(['id_inquiry', 'inquiry_no', 'inquiry_type', 'sub_state', 'cancel_reason'])->keyBy('id_inquiry');
        $items = QuoQuotationItem::whereIn('inquiry_id', $inquiries->keys())->orderBy('line_seq')->get()->groupBy('quotation_id');
        $quotes = QuoQuotation::whereIn('id_quotation', $items->keys())->get()->keyBy('id_quotation');
        $orders = CoOrder::whereIn('quotation_id', $quotes->keys())->get()->keyBy('quotation_id');
        $coItems = CoOrderItem::where('product_id', $productId)->get();
        $ships = CoOrderDelivery::whereIn('item_id', $coItems->pluck('id_item'))->orderBy('ship_date')->get()->groupBy('item_id');

        $rows = $items->map(function ($lines, $qid) use ($quotes, $orders, $inquiries, $coItems, $ships) {
            $q = $quotes[$qid];
            $co = $orders[$qid] ?? null;
            $inq = $inquiries[$lines->first()->inquiry_id];
            $coItem = $co ? $coItems->firstWhere('order_id', $co->id_order) : null;
            $main = $coItem ?? $lines->first();

            return [
                'date' => \Devlib\Locale::d(($co?->co_date ?? $q->issued_date)), 'sort' => ($co?->co_date ?? $q->issued_date)?->timestamp,
                'quotation' => $q->quotation_no, 'co' => $co?->co_no, 'po' => $co?->customer_po_no, 'inquiry' => $inq->inquiry_no, 'type' => $inq->inquiry_type,
                'qty' => (float) $main->qty, 'price' => (float) $main->unit_price,
                'result' => $co ? 'WIN' : ($inq->sub_state === 'LOST' ? 'LOST' : 'OPEN'), 'reason' => $inq->sub_state === 'LOST' ? $inq->cancel_reason : null,
                'tiers' => $lines->map(fn ($l) => ['qty' => (float) $l->qty, 'price' => (float) $l->unit_price])->values()->all(),
                'ships' => $coItem ? ($ships[$coItem->id_item] ?? collect())->map(fn ($d) => ['date' => \Devlib\Locale::d($d->ship_date), 'qty' => (float) $d->ship_qty, 'status' => $d->status])->values()->all() : [],
            ];
        });
        // CO INSTANT (tanpa Quotation) langsung dari Master Product.
        foreach ($coItems->whereNotIn('order_id', $orders->pluck('id_order')) as $ci) {
            $co = CoOrder::find($ci->order_id);
            $rows->push(['date' => \Devlib\Locale::d($co?->co_date), 'sort' => $co?->co_date?->timestamp, 'quotation' => null, 'co' => $co?->co_no, 'po' => $co?->customer_po_no,
                'inquiry' => null, 'type' => 'INSTANT', 'qty' => (float) $ci->qty, 'price' => (float) $ci->unit_price, 'result' => 'WIN', 'reason' => null,
                'tiers' => [['qty' => (float) $ci->qty, 'price' => (float) $ci->unit_price]],
                'ships' => ($ships[$ci->id_item] ?? collect())->map(fn ($d) => ['date' => \Devlib\Locale::d($d->ship_date), 'qty' => (float) $d->ship_qty, 'status' => $d->status])->values()->all()]);
        }

        return $rows->sortByDesc('sort')->values()->all();
    }

    // Versi harga jual dari ProCost (costing published) per inquiry product ini: tanggal & jam terbit + harga per jenjang qty.
    public static function priceVersions(int $productId): array
    {
        return IqpInquiry::where('product_id', $productId)->with(['costing.priceTiers' => fn ($q) => $q->orderBy('qty_pcs')])->get()
            ->filter(fn ($i) => $i->costing?->published_flag)
            ->map(fn ($i) => ['inquiry' => $i->inquiry_no, 'type' => $i->inquiry_type, 'at' => \Devlib\Locale::dt(($i->costing->approved_at ?? $i->costing->updated_at)),
                'sort' => ($i->costing->approved_at ?? $i->costing->updated_at)?->timestamp, 'by' => $i->costing->approved_by,
                'tiers' => $i->costing->priceTiers->map(fn ($t) => ['qty' => (float) $t->qty_pcs, 'price' => (float) $t->price_per_pcs])->all()])
            ->sortByDesc('sort')->values()->all();
    }

    // Part inquiry (dokumen Revisi / sumbernya) untuk form: nilai yang bisa diubah per scope.
    public static function inquiryParts(IqpInquiry $inq): array
    {
        return $inq->parts()->with(['materials' => fn ($q) => $q->orderBy('layer_seq'), 'finishings'])->orderBy('part_seq')->get()->map(fn ($p) => [
            'id' => $p->id_part, 'seq' => $p->part_seq, 'name' => $p->part_name, 'kind' => $p->part_kind, 'join_type_id' => $p->join_type_id,
            'cfg_color' => $p->cfg_color, 'cfg_ink' => $p->cfg_ink, 'dims' => $p->dieline_spec['dims'] ?? [],
            'materials' => $p->materials->map(fn ($m) => ['seq' => $m->layer_seq, 'category_id' => $m->category_id, 'label' => $m->rawmat_name])->all(),
            'finishings' => $p->finishings->pluck('finishing_type_id')->map(fn ($v) => (string) $v)->all(),
            'artwork' => $p->artwork_path ? asset('storage/'.$p->artwork_path) : null, 'artwork_inner' => $p->artwork_inner_path ? asset('storage/'.$p->artwork_inner_path) : null,
        ])->all();
    }

    // Pilihan bahan Revisi: Jenis › Gramatur (mat_category level 3) di bawah Paper & Sheet.
    public static function materialOptions(): array
    {
        $roots = MatCategory::roots()->whereIn('category_name', ['Paper', 'Sheet'])->pluck('category_name', 'id_category');
        $jenis = MatCategory::whereIn('parent_id', $roots->keys())->where('activate', true)->get(['id_category', 'parent_id', 'category_name'])->keyBy('id_category');

        return MatCategory::whereIn('parent_id', $jenis->keys())->where('activate', true)->get(['id_category', 'parent_id', 'category_name'])
            ->mapWithKeys(fn ($g) => [$g->id_category => $roots[$jenis[$g->parent_id]->parent_id].' › '.$jenis[$g->parent_id]->category_name.' › '.trim($g->category_name)])
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)->all();
    }

    // Proses yang biayanya besar untuk ditonjolkan ke Sales (selain proses dasar potong/cetak/QC/kupas/hitung/pack).
    public static function specialProcesses(array $doc): array
    {
        $basic = ['POTONG', 'CETAK', 'QC', 'KUPAS', 'HITUNG', 'PACK', 'QC PACK', 'QC CETAK', 'QC HITUNG'];
        $flat = [];
        $walk = function ($nodes) use (&$walk, &$flat, $basic) {
            foreach ($nodes as $n) {
                foreach ($n['finishings'] as $f) {
                    $flat[] = $f;
                }
                foreach ($n['bop'] as $s) {
                    if (! in_array(mb_strtoupper(trim($s['name'])), $basic, true)) {
                        $flat[] = mb_convert_case(mb_strtolower($s['name']), MB_CASE_TITLE);
                    }
                }
                $walk($n['children']);
            }
        };
        $walk($doc['tree']);

        return array_values(array_unique($flat));
    }

    // Garis keturunan untuk tabel REVISION NOTE Proof Digital: induk paling awal -> product ini.
    public static function lineage(int $productId): array
    {
        $out = [];
        for ($p = ProdProduct::find($productId); $p; $p = $p->parent_product_id ? ProdProduct::find($p->parent_product_id) : null) {
            $note = $p->source_inquiry_id ? IqpInquiry::whereKey($p->source_inquiry_id)->value('revise_reason') : null;
            array_unshift($out, ['design' => $p->design_no ?: $p->product_code, 'note' => $note ?: 'NEW RELEASE', 'date' => ($p->design_date ?? $p->created_at)?->format('d/m/Y')]);
        }

        return $out;
    }
}
