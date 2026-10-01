<?php

namespace App\Services\Sales;

use App\Models\Master\MatCategory;
use App\Models\Master\MatJoinType;
use App\Models\Master\ProdProduct;
use App\Models\Master\RefFinishingType;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sdi\IqpReviseChange;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Inquiry REVISI & REKALKULASI dari Master Product (docs/flow_product_master.md §4).
// Sumber = inquiry yang melahirkan product (prod_product.source_inquiry_id); struktur part disalin utuh
// (cloneStructureFrom). RECALC = product sama, isian hanya Qty + Deadline. REVISE = product turunan (dibuat saat
// Kirim ke ProDev, IqpInquiry::ensureDraftProduct), hanya blok scope yang boleh berubah, tiap perubahan dicatat
// sebelum -> sesudah di iqp_revise_change supaya ProDev & ProCost tahu apa yang wajib diulang.
final class InquiryDerivedWriter
{
    public const SCOPES = ['ARTWORK' => 'Artwork', 'COLOR' => 'Warna', 'DIMENSION' => 'Dimensi', 'MATERIAL' => 'Bahan', 'PROCESS' => 'Proses / Finishing', 'JOIN' => 'Struktur / Sambungan'];

    // Diulang di mana bila scope berubah (dipakai tabel perbedaan).
    public const IMPACT = ['ARTWORK' => 'Proof / ACC Digital · ProCost', 'COLOR' => 'ProDev · ProCost', 'DIMENSION' => 'ProDev (imposisi, pisau) · ProCost',
        'MATERIAL' => 'ProCost', 'PROCESS' => 'ProDev (BOP) · ProCost', 'JOIN' => 'ProDev (BOP rakitan) · ProCost'];

    public function __construct(private string $login) {}

    public static function sourceOf(ProdProduct $product): IqpInquiry
    {
        return IqpInquiry::find($product->source_inquiry_id)
            ?? IqpInquiry::where('product_id', $product->id_product)->whereIn('inquiry_type', ['NEW', 'REVISE'])->orderByDesc('id_inquiry')->first()
            ?? throw ValidationException::withMessages(['product' => 'Product ini belum punya inquiry asal (belum lahir dari Quotation WIN).']);
    }

    public function recalc(ProdProduct $product, ?IqpInquiry $doc, array $in): IqpInquiry
    {
        $this->assertEditable($doc);

        return DB::connection('tm2sdi')->transaction(function () use ($product, $doc, $in) {
            $doc ??= $this->create($product, 'RECALC', ['product_id' => $product->id_product, 'revision_no' => 0, 'sales_note' => 'RECALC dari '.$product->product_name]);
            $doc->update(['deadline_quotation' => $in['deadline_quotation'] ?: null, 'updated_by' => $this->login]);
            $tier = $doc->tiers()->where('is_primary', true)->first();
            $tier ? $tier->update(['qty' => $in['qty'], 'updated_by' => $this->login])
                : $doc->tiers()->create(['tier_seq' => 1, 'qty' => $in['qty'], 'is_primary' => true, 'created_by' => $this->login, 'updated_by' => $this->login]);

            return $doc->refresh();
        });
    }

    public function revise(ProdProduct $product, ?IqpInquiry $doc, array $in, array $artworks = []): IqpInquiry
    {
        $this->assertEditable($doc);
        if (! $in['scope']) {
            throw ValidationException::withMessages(['revise_scope' => 'Scope revisi wajib dipilih minimal satu.']);
        }

        return DB::connection('tm2sdi')->transaction(function () use ($product, $doc, $in, $artworks) {
            $source = self::sourceOf($product);
            $doc ??= $this->create($product, 'REVISE', ['revision_no' => (int) $product->revision_no + 1, 'sales_note' => 'REVISE dari '.$product->product_name.' — '.$in['reason']]);
            $doc->update(['revise_scope' => implode(',', $in['scope']), 'revise_reason' => $in['reason'],
                'design_number' => ($in['design_number'] ?? '') ?: $doc->design_number, 'updated_by' => $this->login]);

            $orig = $source->parts()->with(['materials', 'finishings'])->get()->keyBy('part_seq');
            $writer = new InquiryWriter($this->login);
            foreach ($doc->parts()->with(['materials', 'finishings'])->get() as $part) {
                $e = $in['parts'][$part->part_seq] ?? [];
                $this->applyScope($part, $e, $in['scope']);
                foreach (['artwork' => false, 'artwork_inner' => true] as $f => $inner) {
                    if (in_array('ARTWORK', $in['scope'], true) && isset($artworks[$part->part_seq][$f])) {
                        // Path lama masih milik inquiry asal (dibagi saat disalin) -> lepas dulu supaya file asal tidak ikut terhapus.
                        if ($part->{$inner ? 'artwork_inner_path' : 'artwork_path'} === $orig[$part->part_seq]?->{$inner ? 'artwork_inner_path' : 'artwork_path'}) {
                            $part->update([$inner ? 'artwork_inner_path' : 'artwork_path' => null]);
                        }
                        $writer->storeArtwork($part, $artworks[$part->part_seq][$f], $inner);
                    }
                }
            }
            $this->recordDiff($doc->refresh(), $source, $orig);

            return $doc->refresh();
        });
    }

    public function confirm(IqpInquiry $doc): void
    {
        $this->assertEditable($doc);
        if (! $doc->tiers()->exists()) {
            throw ValidationException::withMessages(['qty' => 'Qty order wajib diisi sebelum Confirm.']);
        }
        $doc->update(['stat_confirm_sales' => true, 'sub_state' => 'SUBMITTED', 'updated_by' => $this->login]);
    }

    // DRAFT belum Confirm boleh dihapus (lifecycle dokumen: Simpan + Hapus + Confirm sebelum Confirm).
    public function delete(IqpInquiry $doc): void
    {
        $this->assertEditable($doc);
        DB::connection('tm2sdi')->transaction(function () use ($doc) {
            $parts = $doc->parts()->pluck('id_part');
            foreach (['iqp_part_material', 'iqp_part_finishing', 'iqp_part_process', 'iqp_part_ink'] as $t) {
                DB::connection('tm2sdi')->table($t)->whereIn('part_id', $parts)->delete();
            }
            $doc->reviseChanges()->delete();
            $doc->parts()->update(['parent_part_id' => null]);
            $doc->parts()->delete();
            $doc->tiers()->delete();
            $doc->delete();
        });
    }

    private function assertEditable(?IqpInquiry $doc): void
    {
        if ($doc?->stat_confirm_sales) {
            throw ValidationException::withMessages(['inquiry' => 'Dokumen sudah Confirm — tidak bisa diubah.']);
        }
    }

    // Header disalin dari inquiry asal; struktur part (rakitan, bahan, tinta, BOP, finishing) disalin utuh.
    private function create(ProdProduct $product, string $type, array $extra): IqpInquiry
    {
        $s = self::sourceOf($product);
        $doc = IqpInquiry::create([
            'inquiry_no' => IqpInquiry::nextInquiryNo(), 'inquiry_date' => now()->toDateString(), 'source' => $s->source, 'pic_sales' => $s->pic_sales,
            'customer_id' => $product->customer_id ?? $s->customer_id, 'is_prospect' => false, 'deadline_quotation' => $s->deadline_quotation,
            'product_draft_name' => $product->product_name, 'inquiry_type' => $type, 'parent_inquiry_id' => $s->id_inquiry,
            'material_source' => $s->material_source, 'job_nature' => $s->job_nature, 'cust_material_spec' => $s->cust_material_spec,
            'tax_scheme' => $s->tax_scheme, 'pph_applicable' => $s->pph_applicable, 'design_number' => $product->design_no ?? $s->design_number,
            'sub_state' => 'DRAFT', 'created_by' => $this->login, 'updated_by' => $this->login,
        ] + $extra)->refresh();
        $s->load(['parts' => fn ($q) => $q->orderBy('part_seq'), 'parts.materials', 'parts.processes', 'parts.finishings', 'parts.inks']);
        $doc->cloneStructureFrom($s);
        // Artwork ikut (dipakai layar & ProDev); file berbagi path sumber sampai diganti lewat scope Artwork.
        foreach ($s->parts as $p) {
            // RECALC = product & part master yang sama -> ProDev membuka part master itu (tidak membuat part baru).
            $doc->parts()->where('part_seq', $p->part_seq)->update(['artwork_path' => $p->artwork_path, 'artwork_original_filename' => $p->artwork_original_filename,
                'artwork_inner_path' => $p->artwork_inner_path, 'artwork_inner_original_filename' => $p->artwork_inner_original_filename]
                + ($type === 'RECALC' ? ['prod_part_id' => $p->prod_part_id] : []));
        }
        if ($type === 'REVISE') {
            foreach ($s->tiers()->orderBy('tier_seq')->get() as $t) {
                $doc->tiers()->create(['tier_seq' => $t->tier_seq, 'qty' => $t->qty, 'is_primary' => $t->is_primary, 'created_by' => $this->login, 'updated_by' => $this->login]);
            }
        }

        return $doc;
    }

    private function applyScope(IqpPart $part, array $e, array $scope): void
    {
        $on = fn ($s) => in_array($s, $scope, true);
        $upd = [];
        if ($on('COLOR') && $part->part_kind === 'COMPONENT') {
            $upd += ['cfg_color' => ($e['cfg_color'] ?? '') ?: $part->cfg_color, 'cfg_ink' => $e['cfg_ink'] ?? $part->cfg_ink];
        }
        if ($on('DIMENSION') && isset($e['dims'])) {
            $spec = $part->dieline_spec ?? [];
            foreach (['product_length', 'product_width', 'product_height'] as $k) {
                if (is_numeric($e['dims'][$k] ?? null)) {
                    $spec['dims'][$k] = (float) $e['dims'][$k];
                }
            }
            $upd['dieline_spec'] = $spec;
        }
        if ($on('JOIN') && $part->part_kind === 'ASSEMBLY' && array_key_exists('join_type_id', $e)) {
            $upd['join_type_id'] = $e['join_type_id'] ?: null;
        }
        if ($upd) {
            $part->update($upd + ['updated_by' => $this->login]);
        }
        if ($on('MATERIAL') && isset($e['materials'])) {
            foreach ($part->materials as $m) {
                $cat = (int) ($e['materials'][$m->layer_seq] ?? 0);
                if ($cat && $cat !== (int) $m->category_id && ($node = MatCategory::find($cat))) {
                    $chain = $node->ancestorChain();
                    $m->update(['category_id' => $cat, 'rawmat_id' => null, 'rawmat_name' => implode(' › ', array_map(fn ($c) => $c->category_name, $chain)),
                        'gram_weight' => preg_match('/(\d+)\s*gr/i', $node->category_name, $g) ? $g[1] : null, 'updated_by' => $this->login]);
                }
            }
        }
        if ($on('PROCESS') && isset($e['finishings'])) {
            $names = RefFinishingType::pluck('finishing_name', 'id_finishing_type');
            $part->finishings()->delete();
            foreach (array_values(array_filter($e['finishings'], fn ($id) => isset($names[$id]))) as $i => $id) {
                $part->finishings()->create(['seq' => ($i + 1) * 10, 'finishing_type_id' => (int) $id, 'finishing_name' => $names[$id], 'created_by' => $this->login, 'updated_by' => $this->login]);
            }
        }
    }

    // Tabel perbedaan ditulis ulang tiap Simpan: bandingkan dokumen REVISE dengan inquiry asal per part & field.
    private function recordDiff(IqpInquiry $doc, IqpInquiry $source, $orig): void
    {
        $doc->reviseChanges()->delete();
        $joins = MatJoinType::pluck('label', 'id_join_type');
        // Nol di belakang hanya dibuang dari pecahan (260.50 -> 260.5); bilangan bulat 260 tetap 260, bukan 26.
        $trim = fn ($v) => str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v;
        $dims = fn ($p) => implode(' × ', array_map(fn ($k) => $trim($p->dieline_spec['dims'][$k] ?? '-'), ['product_length', 'product_width', 'product_height'])).' mm';
        $add = fn ($part, $scope, $label, $old, $new) => (string) $old === (string) $new ? null : IqpReviseChange::create([
            'inquiry_id' => $doc->id_inquiry, 'part_id' => $part?->id_part, 'scope' => $scope, 'field_label' => $label,
            'old_value' => mb_substr((string) $old, 0, 500), 'new_value' => mb_substr((string) $new, 0, 500), 'impact' => self::IMPACT[$scope],
            'created_by' => $this->login, 'updated_by' => $this->login]);

        $add(null, 'ARTWORK', 'No. Desain', $source->design_number, $doc->design_number);
        foreach ($doc->parts()->with(['materials', 'finishings'])->orderBy('part_seq')->get() as $p) {
            $o = $orig[$p->part_seq] ?? null;
            if (! $o) {
                continue;
            }
            $add($p, 'ARTWORK', 'Artwork sisi luar', $o->artwork_original_filename, $p->artwork_original_filename);
            $add($p, 'ARTWORK', 'Artwork sisi dalam', $o->artwork_inner_original_filename, $p->artwork_inner_original_filename);
            $add($p, 'COLOR', 'Notasi warna', $o->cfg_color, $p->cfg_color);
            $add($p, 'COLOR', 'Tinta', $o->cfg_ink, $p->cfg_ink);
            $add($p, 'DIMENSION', 'Ukuran (P × L × T)', $dims($o), $dims($p));
            $add($p, 'JOIN', 'Sambungan', $joins[$o->join_type_id] ?? '—', $joins[$p->join_type_id] ?? '—');
            $mo = $o->materials->keyBy('layer_seq');
            foreach ($p->materials as $m) {
                $add($p, 'MATERIAL', 'Bahan #'.$m->layer_seq, $mo[$m->layer_seq]->rawmat_name ?? '—', $m->rawmat_name);
            }
            $add($p, 'PROCESS', 'Finishing', $o->finishings->pluck('finishing_name')->implode(', ') ?: '—', $p->finishings->pluck('finishing_name')->implode(', ') ?: '—');
        }
    }

    // Artwork baru per part (dikunci part_seq) dari form: parts[<seq>][artwork|artwork_inner].
    public static function artworks(array $files): array
    {
        return collect($files)->map(fn ($f) => array_filter(['artwork' => $f['artwork'] ?? null, 'artwork_inner' => $f['artwork_inner'] ?? null], fn ($x) => $x instanceof UploadedFile))->filter()->all();
    }
}
