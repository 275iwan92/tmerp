<?php

namespace App\Services\Sales;

use App\Models\Master\ProdPart;
use App\Models\Master\ProdPartFinishing;
use App\Models\Master\ProdProduct;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Quotation WIN -> inquiry menjadi DATA POKOK Master Product (docs/flow_product_master.md §2-§4).
// Product DRAFT yang dibuat saat Kirim ke ProDev diresmikan & dilengkapi: part (termasuk rakitan +
// sambungan), dieline & bentangan, artwork luar/dalam, finishing; bahan/tinta/BOP yang sudah ditulis
// ProDev (SpecEngine) dipertahankan, hanya diisi dari inquiry bila ProDev belum pernah menulisnya.
// REVISE = product turunan (cabang, lihat IqpInquiry::ensureDraftProduct); RECALC = product yang sama.
final class ProductMasterSync
{
    public function __construct(private string $login) {}

    public function fromInquiry(IqpInquiry $inq): ProdProduct
    {
        $inq->loadMissing(['parts' => fn ($q) => $q->orderBy('part_seq'), 'parts.materials', 'parts.processes', 'parts.finishings', 'parts.inks']);
        $product = ProdProduct::findOrFail($inq->ensureDraftProduct($this->login));

        return DB::connection('tm2mst')->transaction(function () use ($inq, $product) {
            $first = $product->card_status !== 'ACTIVE';
            $dims = $inq->parts->firstWhere('part_kind', 'COMPONENT')?->dieline_spec['dims'] ?? [];
            $cm = fn (string $k) => isset($dims[$k]) ? round(((float) $dims[$k]) / 10, 2) : null;

            $product->update(array_filter([
                // Nama diubah manual oleh Sales di master sesudahnya -> hanya diisi saat pertama resmi.
                'product_name' => $first ? ($inq->product_draft_name ?: $product->product_name) : null,
                'customer_id' => $inq->customer_id,
                'design_no' => $inq->design_number,
                'cm_length' => $cm('product_length'),
                'cm_width' => $cm('product_width'),
                'cm_height' => $cm('product_height'),
                'source_inquiry_id' => $inq->id_inquiry,
                'card_status' => 'ACTIVE',
                'stat_confirm' => true,
                'updated_by' => $this->login,
            ], fn ($v) => $v !== null));

            // RECALC: spec 100% sama dgn product asalnya -> tidak ada part yang disalin ulang.
            if ($inq->inquiry_type !== 'RECALC') {
                $map = [];
                foreach ($inq->parts as $p) {
                    $map[$p->id_part] = $this->part($product, $p);
                }
                foreach ($inq->parts as $p) {
                    $map[$p->id_part]->update(['parent_part_id' => $p->parent_part_id ? ($map[$p->parent_part_id]->id_part ?? null) : null]);
                }
            }

            return $product->refresh();
        });
    }

    private function part(ProdProduct $product, IqpPart $p): ProdPart
    {
        $dims = $p->dieline_spec['dims'] ?? [];
        $cm = fn (string $k) => isset($dims[$k]) ? round(((float) $dims[$k]) / 10, 2) : null;
        $data = [
            'product_id' => $product->id_product,
            'part_name' => $p->part_name,
            'part_type' => $p->part_type,
            'part_kind' => $p->part_kind,
            'join_type_id' => $p->join_type_id,
            'qty_in_parent' => $p->qty_in_parent,
            'qty_per_sku' => $p->qty_per_sku,
            'pack_qty' => $p->pack_qty,
            'cfg_color' => $p->cfg_color ? mb_substr($p->cfg_color, 0, 5) : null,
            'cfg_ink' => $p->cfg_ink,
            'dieline_spec' => $p->dieline_spec,
            'flat_w' => $p->flat_w,
            'flat_h' => $p->flat_h,
            'flat_area' => $p->flat_area,
            'cm_length' => $cm('product_length'),
            'cm_width' => $cm('product_width'),
            'cm_height' => $cm('product_height'),
            'source_iqp_part_id' => $p->id_part,
            'activate' => true,
            'updated_by' => $this->login,
        ];

        $part = $p->prod_part_id ? ProdPart::find($p->prod_part_id) : null;
        if ($part) {
            $part->update($data);
        } else {
            $part = ProdPart::create($data + ['part_code' => 'P'.$p->id_part, 'created_by' => $this->login]);
            $p->update(['prod_part_id' => $part->id_part]);
        }

        $this->finishings($part, $p);
        $this->fillIfEmpty($part, $p);
        $this->artwork($part, $p->artwork_path, $p->artwork_original_filename, 'OUTER');
        $this->artwork($part, $p->artwork_inner_path, $p->artwork_inner_original_filename, 'INNER');

        return $part;
    }

    private function finishings(ProdPart $part, IqpPart $p): void
    {
        ProdPartFinishing::where('part_id', $part->id_part)->delete();
        foreach ($p->finishings as $f) {
            ProdPartFinishing::create(['part_id' => $part->id_part, 'seq' => $f->seq, 'finishing_type_id' => $f->finishing_type_id,
                'attribute' => $f->attribute, 'notes' => $f->notes, 'created_by' => $this->login, 'updated_by' => $this->login]);
        }
    }

    // Bahan, tinta, BOP milik ProDev (SpecEngine menulis langsung ke master) -> dipertahankan; kosong = ambil dari inquiry.
    private function fillIfEmpty(ProdPart $part, IqpPart $p): void
    {
        $mst = DB::connection('tm2mst');
        $now = now();
        $audit = ['created_at' => $now, 'updated_at' => $now, 'created_by' => $this->login, 'updated_by' => $this->login];

        if (! $mst->table('prod_part_material')->where('part_id', $part->id_part)->exists()) {
            $mst->table('prod_part_material')->insert($p->materials->sortBy('layer_seq')->values()->map(fn ($m, $i) => [
                'part_id' => $part->id_part, 'layer_seq' => $i + 1, 'role' => 'SUBSTRATE', 'material_id' => $m->rawmat_id, 'category_id' => $m->category_id,
                'gram_weight' => $m->gram_weight, 'flute_type' => $m->flute_type ? mb_substr($m->flute_type, 0, 10) : null,
                'size_p' => $m->size_p, 'size_l' => $m->size_l, 'qty_per_sheet' => $m->qty_per_sheet, 'uom' => $m->uom,
                'is_primary' => $m->is_primary, 'note' => $m->rawmat_name, 'source' => 'INQUIRY',
            ] + $audit)->all());
        } else {
            // Jenis/Gramatur pilihan Sales tetap tercatat di baris ProDev dengan urutan yang sama.
            foreach ($p->materials->whereNotNull('category_id') as $m) {
                $mst->table('prod_part_material')->where('part_id', $part->id_part)->where('layer_seq', $m->layer_seq)->whereNull('category_id')
                    ->update(['category_id' => $m->category_id]);
            }
        }

        if (! $mst->table('prod_part_ink')->where('part_id', $part->id_part)->exists()) {
            $mst->table('prod_part_ink')->insert($p->inks->map(fn ($k) => $k->only(['pass_seq', 'pass_label', 'unit_no', 'ink_name', 'coverage_pct', 'coverage_cm2', 'color_hex'])
                + ['part_id' => $part->id_part, 'source' => 'INQUIRY', 'created_at' => $now, 'created_by' => $this->login])->all());
        }

        if (! $mst->table('prod_part_process')->where('part_id', $part->id_part)->exists()) {
            $mst->table('prod_part_process')->insert($p->processes->sortBy('step_seq')->values()->map(fn ($s) => [
                'part_id' => $part->id_part, 'branch_code' => $s->branch_code, 'step_seq' => $s->step_seq, 'flow_type' => $s->flow_type,
                'process_id' => $s->process_id, 'process_name' => $s->process_name ? mb_substr($s->process_name, 0, 80) : null, 'machine_id' => $s->machine_id,
                'subcon_supplier_id' => $s->subcon_supplier_id, 'qty_factor' => $s->qty_factor, 'waste_pct' => $s->waste_pct, 'tolerance_pct' => $s->tolerance_pct,
                'target_qty_per_hour' => $s->target_qty_per_hour, 'note' => $s->note, 'source' => 'INQUIRY',
            ] + $audit)->all());
        }
    }

    // Artwork master berversi (prod_part_artwork). File disalin ke folder product supaya tidak ikut terhapus
    // bila artwork di inquiry diganti.
    private function artwork(ProdPart $part, ?string $path, ?string $name, string $side): void
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return;
        }
        $mst = DB::connection('tm2mst');
        $last = $mst->table('prod_part_artwork')->where('part_id', $part->id_part)->where('side', $side)->orderByDesc('version_no')->first();
        if ($last && $last->original_filename === $name && $last->artwork_path && Storage::disk('public')->size($last->artwork_path) === Storage::disk('public')->size($path)) {
            return;
        }
        $target = "product/{$part->id_part}/artwork-".strtolower($side).'-v'.(($last->version_no ?? 0) + 1).'.'.pathinfo($path, PATHINFO_EXTENSION);
        Storage::disk('public')->copy($path, $target);
        $mst->table('prod_part_artwork')->where('part_id', $part->id_part)->where('side', $side)->update(['activate' => false]);
        $mst->table('prod_part_artwork')->insert([
            'part_id' => $part->id_part, 'version_no' => ($last->version_no ?? 0) + 1, 'side' => $side, 'artwork_path' => $target,
            'original_filename' => $name, 'proof_required' => true, 'is_approved' => false, 'activate' => true,
            'created_at' => now(), 'created_by' => $this->login,
        ]);
    }
}
