<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Master Card Collection (user 2026-10-01, mockup docs/mockups/master_card_collection_v2.jpg): kartu induk lama (Excel sheet "MC"
// atau foto) dikumpulkan dulu, dilengkapi, lalu dijadikan Master Product atau Inquiry. Tabel ini = STAGING hasil baca kartu:
// bahan/finishing/proses/warna/ACC disimpan JSON apa adanya dari kartu (teks bebas lama, belum tentu cocok master) dan baru
// dinormalisasi ke iqp_* / prod_* saat konversi. Setiap kartu WAJIB menyimpan SVG dieline mockup (dieline_svg, user 2026-10-01).
// Menu: Sales > Inquiry & Quotation > Pro-Dev > Master Card Collection (v2:master-card-collection).
return new class extends Migration
{
    protected $connection = 'tm2mst';

    public function up(): void
    {
        Schema::connection('tm2mst')->create('prod_master_card', function (Blueprint $t) {
            $t->id('id_master_card');
            $t->string('source_kind', 10)->default('XLSX')->comment('XLSX | PHOTO | MANUAL');
            $t->string('status', 12)->default('INCOMPLETE')->index()->comment('INCOMPLETE | READY | PRODUCT | INQUIRY');
            $t->string('card_code', 80)->nullable()->comment('Kode Item di kartu');
            $t->string('design_no', 30)->nullable()->index();
            $t->string('product_name', 200)->nullable();
            $t->string('group_key', 200)->nullable()->index()->comment('kartu per part (A/B) dengan kunci sama = 1 produk');
            $t->string('part_label', 10)->nullable()->comment('A | B | kosong = kartu utuh');
            $t->date('card_date')->nullable();
            $t->string('customer_text', 150)->nullable()->comment('nama customer apa adanya di kartu');
            $t->unsignedBigInteger('customer_id')->nullable()->comment('logis -> tm2mst.cust_customer');
            $t->decimal('dim_l', 10, 2)->nullable()->comment('mm');
            $t->decimal('dim_w', 10, 2)->nullable()->comment('mm');
            $t->decimal('dim_h', 10, 2)->nullable()->comment('mm');
            $t->string('knife_code', 200)->nullable();
            $t->decimal('knife_w', 10, 2)->nullable()->comment('mm, ukuran pisau di kartu');
            $t->decimal('knife_h', 10, 2)->nullable();
            $t->string('up_text', 20)->nullable()->comment('Isi dalam satu cetakan, mis. 8 UP / 1/2 UP / 1 SET');
            $t->decimal('print_w', 10, 2)->nullable();
            $t->decimal('print_h', 10, 2)->nullable();
            $t->string('join_text', 40)->nullable()->comment('Bentuk sambungan di kartu');
            $t->unsignedBigInteger('join_type_id')->nullable()->comment('-> mat_join_type');
            $t->string('type_code', 40)->nullable()->comment('Tipe Produk (dieline) -> prod_type.type_code');
            $t->json('dims_json')->nullable()->comment('parameter dieline lengkap (product_length.. + glue_flap dst)');
            $t->decimal('flat_w', 10, 2)->nullable();
            $t->decimal('flat_h', 10, 2)->nullable();
            $t->mediumText('dieline_svg')->nullable()->comment('SVG dieline mockup (App\\Support\\Dieline\\TmDieline)');
            $t->json('materials_json')->nullable();
            $t->json('finishing_json')->nullable();
            $t->json('process_json')->nullable();
            $t->json('colors_json')->nullable();
            $t->json('acc_json')->nullable();
            $t->unsignedInteger('pack_qty')->nullable();
            $t->text('notes')->nullable()->comment('Keterangan di kartu');
            $t->json('raw_json')->nullable()->comment('semua sel terbaca, untuk audit');
            $t->unsignedBigInteger('product_id')->nullable()->comment('hasil Make Master Product -> prod_product');
            $t->unsignedBigInteger('inquiry_id')->nullable()->comment('hasil Make Inquiry -> tm2sdi.iqp_inquiry');
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $t->string('data_origin', 10)->default('LIVE')->index()->comment('LIVE | DUMMY | LEGACY');
        });

        Schema::connection('tm2mst')->create('prod_master_card_file', function (Blueprint $t) {
            $t->id('id_master_card_file');
            $t->foreignId('master_card_id')->constrained('prod_master_card', 'id_master_card')->cascadeOnDelete();
            $t->string('file_kind', 12)->comment('SOURCE (xlsx) | PHOTO | PROOF | CUT_PLAN | ARTWORK');
            $t->string('part_label', 10)->nullable()->comment('artwork per part A/B');
            $t->string('path', 255);
            $t->string('original_name', 255)->nullable();
            $t->string('mime', 80)->nullable();
            $t->unsignedInteger('size')->nullable();
            $t->unsignedSmallInteger('seq')->default(1);
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
        });

        $sys = DB::connection('tm2sys');
        $group = $sys->table('sys_app_menu')->where('code', 'SALES_PRODEVGRP')->value('id');
        if ($group && ! $sys->table('sys_app_menu')->where('code', 'SALES_MCARD')->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => $group, 'code' => 'SALES_MCARD', 'label' => 'Master Card Collection', 'icon' => 'fa-solid fa-id-card',
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:master-card-collection', 'target_kind' => 'app', 'sort_order' => 30, 'activate' => 1,
                'status_code' => 'READY_FOR_QC', 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SALES_MCARD')->delete();
        Schema::connection('tm2mst')->dropIfExists('prod_master_card_file');
        Schema::connection('tm2mst')->dropIfExists('prod_master_card');
    }
};
