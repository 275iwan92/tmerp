<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Sampel UAT AL SAMSI Top diselaraskan ke Master Card asli (DS003047,
// persetujuan user 2026-09-17) supaya jadi uji terima engine BOM/Ink/BOP:
// dari input Inquiry di bawah, engine harus menghasilkan routing 8 langkah,
// Warna Pertama CS (K-C-M-Y) dan plastik laminating 85 cm seperti kartunya.
//
// Bahan "DUPLEX IKA DP 350 gsm 84,5 x 72" belum ada di mat_raw (hanya ukuran
// plano lain), jadi dibuatkan item baru (instruksi user 2026-09-17: "Buat saja
// Duplex Ika DP nya") dengan pola isian item sejenis "DC 350 GR IKA DP ...".
return new class extends Migration
{
    private const CODE = 'UAT-ALSAMSI-TOP';

    private const MATERIAL_NAME = 'DUPLEX IKA DP 350 GR 84.5 X 72 CM';

    // Kategori 5 level (mat_category): Paper > Duplex > 350gr > 84,5 X 72 CM >
    // IKA DP. Cabang 350gr belum punya level 4/5, jadi dibuat. Prefix level 4
    // (2 digit) & level 5 (3 digit) melanjutkan nomor urut di bawah Duplex,
    // sama pola node yang sudah ada (PDA01, PDA01012, ...).
    private function ensureCategory($mst, $now): array
    {
        $paper = $mst->table('mat_category')->where('level', 1)->where('category_name', 'Paper')->first();
        $duplex = $mst->table('mat_category')->where('parent_id', $paper->id_category)->where('category_name', 'Duplex')->first();
        $gsm = $mst->table('mat_category')->where('parent_id', $duplex->id_category)->where('category_name', '350gr')->first();

        $l3Ids = $mst->table('mat_category')->where('parent_id', $duplex->id_category)->pluck('id_category');
        $l4Ids = $mst->table('mat_category')->whereIn('parent_id', $l3Ids)->pluck('id_category');
        $audit = ['activate' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'claude_code', 'updated_by' => 'claude_code'];

        $size = $mst->table('mat_category')->where('parent_id', $gsm->id_category)->where('category_name', '84,5 X 72 CM')->first();
        if (! $size) {
            $next = (int) $mst->table('mat_category')->whereIn('id_category', $l4Ids)->max(DB::raw('CAST(prefix_char AS UNSIGNED)')) + 1;
            $id = $mst->table('mat_category')->insertGetId([
                'parent_id' => $gsm->id_category, 'level' => 4, 'category_name' => '84,5 X 72 CM',
                'prefix_char' => str_pad((string) $next, 2, '0', STR_PAD_LEFT),
            ] + $audit, 'id_category');
            $size = $mst->table('mat_category')->where('id_category', $id)->first();
            $l4Ids->push($id);
        }

        $brand = $mst->table('mat_category')->where('parent_id', $size->id_category)->where('category_name', 'IKA DP')->first();
        if (! $brand) {
            $next = (int) $mst->table('mat_category')->whereIn('parent_id', $l4Ids)->max(DB::raw('CAST(prefix_char AS UNSIGNED)')) + 1;
            $id = $mst->table('mat_category')->insertGetId([
                'parent_id' => $size->id_category, 'level' => 5, 'category_name' => 'IKA DP',
                'prefix_char' => str_pad((string) $next, 3, '0', STR_PAD_LEFT),
            ] + $audit, 'id_category');
            $brand = $mst->table('mat_category')->where('id_category', $id)->first();
        }

        $chain = [$paper, $duplex, $gsm, $size, $brand];

        return [
            'names' => array_map(fn ($n) => $n->category_name, $chain),
            'prefix' => implode('', array_map(fn ($n) => $n->prefix_char, $chain)),
        ];
    }

    private function ensureMaterial($mst, $now): array
    {
        $cat = $this->ensureCategory($mst, $now);
        $row = $mst->table('mat_raw')->where('material_name', self::MATERIAL_NAME)->first();

        // Kode = gabungan prefix kategori, + angka bila sudah dipakai (sama
        // dengan regenerateCode() di layar Raw Material).
        $code = $cat['prefix'];
        for ($n = 2; $mst->table('mat_raw')->where('material_code', $code)->where('material_id', '<>', $row?->material_id ?? 0)->exists(); $n++) {
            $code = $cat['prefix'].$n;
        }

        $categories = [
            'material_code' => $code,
            'category1' => $cat['names'][0],
            'category2' => $cat['names'][1],
            'category3' => $cat['names'][2],
            'category4' => $cat['names'][3],
            'category5' => $cat['names'][4],
        ];

        if ($row) {
            $mst->table('mat_raw')->where('material_id', $row->material_id)->update($categories + ['update_log' => $now, 'update_by' => 'claude_code']);

            return [$row->material_id, $row->material_name];
        }

        $sibling = $mst->table('mat_raw')->where('material_name', 'like', 'DC 350 GR IKA DP%')->orderBy('material_id')->first();

        $id = $mst->table('mat_raw')->insertGetId($categories + [
            'material_name' => self::MATERIAL_NAME,
            'item_type' => 'MATERIAL',
            'description' => 'Duplex IKA DP 350 gsm, lembar cetak Master Card DS003047',
            'gram_weight' => 350,
            'cm_length' => 84.5,
            'cm_width' => 72,
            'mm_thickness' => 0,
            'qc_required' => 0,
            'uom_stock' => 'LBR',
            'uom_purchase' => 'RIM',
            'purchasable' => 1,
            'uom_usage' => 'PCS',
            'conversion_to_stock' => 1,
            'purchase_tax_code' => 'PPN',
            'min_stock' => 0,
            'activate' => 1,
            'costing_method' => 'AVG',
            'is_fx_sensitive' => 0,
            'stock_asset_account_id' => $sibling?->stock_asset_account_id,
            'cogs_account_id' => $sibling?->cogs_account_id,
            'gr_ir_account_id' => $sibling?->gr_ir_account_id,
            'stat_confirm' => 1,
            'confirmed_at' => $now,
            'confirmed_by' => 'claude_code',
            'create_log' => $now,
            'update_log' => $now,
            'create_by' => 'claude_code',
            'update_by' => 'claude_code',
        ], 'material_id');

        return [$id, self::MATERIAL_NAME];
    }

    private const FINISHINGS = [
        ['Laminating Doff', null],
        ['UV Spot', null],
        ['Hot Print', 'Gold Puyeh'],
        ['Emboss', null],
    ];

    public function up(): void
    {
        $mst = DB::connection('tm2mst');
        $sdi = DB::connection('tm2sdi');
        $now = now();
        $by = ['updated_at' => $now, 'updated_by' => 'claude_code'];

        $part = $mst->table('prod_part')->where('mastercard_code', self::CODE)->first();
        if (! $part) {
            return;
        }

        $customerId = $mst->table('cust_customer')->where('customer_name', 'PAK MUKRI')->orderBy('id_customer')->value('id_customer');

        $mst->table('prod_product')->where('id_product', $part->product_id)->update([
            'product_name' => 'ALSAMSI EXCLUSIVE TOP',
            'design_no' => 'DS003047',
            'customer_id' => $customerId,
            'cm_length' => 24.8, 'cm_width' => 19.0, 'cm_height' => 3.4,
        ] + $by);

        $mst->table('prod_part')->where('id_part', $part->id_part)->update([
            'cm_length' => 24.8, 'cm_width' => 19.0, 'cm_height' => 3.4,
            'cfg_color' => '4/0',
            'pack_qty' => 400,
            'finishing' => 'Laminating Doff, UV Spot, Hot Print Gold Puyeh, Emboss',
        ] + $by);

        $iqp = $sdi->table('iqp_part')->where('prod_part_id', $part->id_part)->first();
        if (! $iqp) {
            return;
        }

        $sdi->table('iqp_inquiry')->where('id_inquiry', $iqp->inquiry_id)->update([
            'customer_id' => $customerId,
            'product_draft_name' => 'ALSAMSI EXCLUSIVE TOP',
            'design_number' => 'DS003047',
            'parent_product_id' => $part->product_id,
        ] + $by);

        $sdi->table('iqp_part')->where('id_part', $iqp->id_part)->update(['pack_qty' => 400, 'cfg_color' => '4/0'] + $by);

        $audit = ['created_at' => $now, 'updated_at' => $now, 'created_by' => 'claude_code', 'updated_by' => 'claude_code'];

        [$materialId, $materialName] = $this->ensureMaterial($mst, $now);

        $sdi->table('iqp_part_material')->where('part_id', $iqp->id_part)->delete();
        $sdi->table('iqp_part_material')->insert([
            'part_id' => $iqp->id_part, 'layer_seq' => 1, 'rawmat_id' => $materialId,
            'rawmat_name' => $materialName, 'gram_weight' => 350, 'size_p' => 84.5, 'size_l' => 72, 'is_primary' => 1,
            'material_source' => 'OWN', 'source' => 'MANUAL',
        ] + $audit);

        $types = $mst->table('ref_finishing_type')->pluck('id_finishing_type', 'finishing_name');
        $sdi->table('iqp_part_finishing')->where('part_id', $iqp->id_part)->delete();
        foreach (self::FINISHINGS as $i => [$name, $attr]) {
            $sdi->table('iqp_part_finishing')->insert([
                'part_id' => $iqp->id_part, 'seq' => ($i + 1) * 10,
                'finishing_type_id' => $types[$name], 'finishing_name' => $name, 'attribute' => $attr,
            ] + $audit);
        }
    }

    public function down(): void
    {
        $part = DB::connection('tm2mst')->table('prod_part')->where('mastercard_code', self::CODE)->first();
        $iqpId = $part ? DB::connection('tm2sdi')->table('iqp_part')->where('prod_part_id', $part->id_part)->value('id_part') : null;

        if ($iqpId) {
            DB::connection('tm2sdi')->table('iqp_part_finishing')->where('part_id', $iqpId)->delete();
            DB::connection('tm2sdi')->table('iqp_part_material')->where('part_id', $iqpId)->delete();
        }
        // Item bahan tidak dihapus (bisa keburu dipakai transaksi). Nilai
        // identitas (nama/pelanggan/dimensi) juga tidak dikembalikan: versi
        // sebelumnya tebakan dari mockup, bukan data yang lebih benar.
    }
};
