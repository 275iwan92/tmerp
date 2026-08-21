<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Master Produk (menu MD_PROD) -- prod_category & prod_product SUDAH ada di
// DB (live dari docs/tm2mst.sql, bukan dibuat migration ini) tapi keduanya
// kosong (0 baris). Isi data dummy secukupnya supaya layar grid yang baru
// dibangun tidak kosong melompong -- feedback_keep_dummy_data: selalu isi
// data uji utk layar baru, jangan dihapus lagi setelahnya.
//
// prod_product.design_no/card_status dan prod_part.joint_type/finishing/
// up_per_print/pack_qty SUDAH ada di kolom live (delta TIER_3A_MASTER_CARD.sql
// ternyata sudah dieksekusi ke DB walau tidak tercermin di dump tm2mst.sql
// 13 Aug) -- TIDAK dipakai di sini, itu domain layar "Master Card" terpisah
// (PROD_CARD, modul Production) yang belum digarap. Grid ini cuma header
// prod_product standar, paralel ke Customer/Supplier/dst.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2mst');

        $db->table('prod_category')->insertOrIgnore([
            ['category_name' => 'Corrugated Box', 'description' => 'Kotak karton gelombang (RSC, die-cut, dsb.)', 'created_by' => 'system', 'updated_by' => 'system'],
            ['category_name' => 'Folding Box', 'description' => 'Box lipat duplex/ivory untuk kemasan retail', 'created_by' => 'system', 'updated_by' => 'system'],
            ['category_name' => 'Label & Sticker', 'description' => 'Label self-adhesive, sticker produk', 'created_by' => 'system', 'updated_by' => 'system'],
            ['category_name' => 'Hang Tag', 'description' => 'Tag gantung untuk produk garmen/aksesoris', 'created_by' => 'system', 'updated_by' => 'system'],
            ['category_name' => 'Display & POP', 'description' => 'Display rak, point-of-purchase, standee', 'created_by' => 'system', 'updated_by' => 'system'],
        ]);

        $catId = fn (string $name) => $db->table('prod_category')->where('category_name', $name)->value('id_category');
        $custId = $db->table('cust_customer')->where('is_active', true)->orderBy('id_customer')->value('id_customer');

        $db->table('prod_product')->insertOrIgnore([
            [
                'product_code' => 'FB-DUPLEX-001', 'product_name' => 'Folding Box Duplex 350gr Kosmetik',
                'customer_id' => $custId, 'category_id' => $catId('Folding Box'),
                'uom_stock' => 'PCS', 'uom_sales' => 'PCS', 'conversion_to_stock' => 1,
                'cm_length' => 12, 'cm_width' => 8, 'cm_height' => 4,
                'min_stock' => 500, 'max_stock' => 5000, 'cur_stock' => 0,
                'default_selling_price' => 850, 'minimum_selling_price' => 700,
                'is_confirmed' => 1, 'is_active' => 1, 'created_by' => 'system', 'updated_by' => 'system',
            ],
            [
                'product_code' => 'RSC-CORR-002', 'product_name' => 'RSC Box Corrugated 5-Ply Ekspor',
                'customer_id' => null, 'category_id' => $catId('Corrugated Box'),
                'uom_stock' => 'PCS', 'uom_sales' => 'PCS', 'conversion_to_stock' => 1,
                'cm_length' => 40, 'cm_width' => 30, 'cm_height' => 25,
                'min_stock' => 100, 'max_stock' => 2000, 'cur_stock' => 0,
                'default_selling_price' => 12500, 'minimum_selling_price' => 10000,
                'is_confirmed' => 0, 'is_active' => 1, 'created_by' => 'system', 'updated_by' => 'system',
            ],
            [
                'product_code' => 'LBL-STK-003', 'product_name' => 'Sticker Label Bulat 5cm Vinyl',
                'customer_id' => $custId, 'category_id' => $catId('Label & Sticker'),
                'uom_stock' => 'LBR', 'uom_sales' => 'ROLL', 'conversion_to_stock' => 500,
                'cm_length' => 5, 'cm_width' => 5, 'cm_height' => 0,
                'min_stock' => 1000, 'max_stock' => 20000, 'cur_stock' => 0,
                'default_selling_price' => 150, 'minimum_selling_price' => 120,
                'is_confirmed' => 0, 'is_active' => 1, 'created_by' => 'system', 'updated_by' => 'system',
            ],
            [
                'product_code' => 'TAG-HNG-004', 'product_name' => 'Hang Tag Ivory 260gr + Tali',
                'customer_id' => null, 'category_id' => $catId('Hang Tag'),
                'uom_stock' => 'PCS', 'uom_sales' => 'PCS', 'conversion_to_stock' => 1,
                'cm_length' => 6, 'cm_width' => 3, 'cm_height' => 0,
                'min_stock' => 2000, 'max_stock' => 30000, 'cur_stock' => 0,
                'default_selling_price' => 320, 'minimum_selling_price' => 250,
                'is_confirmed' => 1, 'is_active' => 1, 'created_by' => 'system', 'updated_by' => 'system',
            ],
            [
                'product_code' => 'DSP-POP-005', 'product_name' => 'Display Standee Corrugated 60x40',
                'customer_id' => null, 'category_id' => $catId('Display & POP'),
                'uom_stock' => 'PCS', 'uom_sales' => 'PCS', 'conversion_to_stock' => 1,
                'cm_length' => 60, 'cm_width' => 40, 'cm_height' => 20,
                'min_stock' => 10, 'max_stock' => 200, 'cur_stock' => 0,
                'default_selling_price' => 45000, 'minimum_selling_price' => 38000,
                'is_confirmed' => 0, 'is_active' => 0, 'created_by' => 'system', 'updated_by' => 'system',
            ],
        ]);
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');
        $db->table('prod_product')->whereIn('product_code', ['FB-DUPLEX-001', 'RSC-CORR-002', 'LBL-STK-003', 'TAG-HNG-004', 'DSP-POP-005'])->delete();
        $db->table('prod_category')->whereIn('category_name', ['Corrugated Box', 'Folding Box', 'Label & Sticker', 'Hang Tag', 'Display & POP'])->delete();
    }
};
