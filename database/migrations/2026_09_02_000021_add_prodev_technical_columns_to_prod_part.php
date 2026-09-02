<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/inquiry_product_costing/productdevelopment_tier1.md §4.2 -- perluasan
// prod_part dgn kolom teknis ProDev (Master Card). SEBELUM menulis migration
// ini, prod_part di-cek ulang ke docs/ddl/tm2mst.sql (DDL live) -- ternyata
// SUDAH ADA beberapa kolom yang tumpang tindih dgn rencana §4.2, jadi
// DIPAKAI ULANG (bukan dobel) supaya konsisten CLAUDE.md §2 "Master netral,
// single source of truth":
// - printsize_p/printsize_l (decimal 8,2, sudah ada)  = print_area_w_cm/h_cm
//   rencana §4.2 -- nama beda tapi maksud sama (ukuran area cetak).
// - joint_type (varchar, sudah ada, TYPO "joint" bukan "join" di kode lama)
//   = join_type rencana §4.2.
// - pack_qty (int, sudah ada)                          = pack_qty rencana §4.2.
// - prod_product.design_no (sudah ada di level PRODUCT, bukan PART) = No.
//   Desain -- konsisten §4.2b (dimensi/identitas desain di level produk).
// Kolom BARU beneran (gap nyata, dicek tak ada padanan): mastercard_code,
// die_code/die_w_cm/die_h_cm (beda dari ukuran cetak -- ukuran fisik pisau
// pond), sheet_raw_w_cm/h_cm (ukuran bahan mentah sblm potong, beda dari
// print area), color_set_1/2 (JSON per-unit CMYK/spot, beda dari cfg_color/
// cfg_ink yg sudah ada -- itu kode ringkas '4/4' & catatan bebas, bukan
// daftar warna per-unit terstruktur), 6 kolom flap (§4.3a Tingkat 1 & 2 --
// gap nyata, tak ada satu pun representasi lidah sebelumnya).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_part', function (Blueprint $table) {
            $table->string('mastercard_code', 50)->nullable()->after('part_code')
                ->comment('Kode Item Master Card. Placeholder MC-{id_part} sampai pola mnemonic final diputuskan (tier1 §7 poin 3)');
            $table->string('die_code', 100)->nullable()->comment('Kode/rujukan fisik pisau pond, mis. "PISAU ALSAMSI TOP ISI 4 MATA"');
            $table->decimal('die_w_cm', 8, 2)->nullable()->comment('Ukuran fisik pisau pond (lebih besar dari area cetak krn margin gripper) -- BEDA dari printsize_p');
            $table->decimal('die_h_cm', 8, 2)->nullable();
            $table->decimal('sheet_raw_w_cm', 8, 2)->nullable()->comment('Ukuran bahan mentah sebelum dipotong -- BEDA dari printsize_p (area cetak)');
            $table->decimal('sheet_raw_h_cm', 8, 2)->nullable();
            $table->json('color_set_1')->nullable()->comment('Warna Unit 1-4 sisi pertama, array string mis. ["Black","Cyan","Magenta","Yellow"]');
            $table->json('color_set_2')->nullable()->comment('Warna Unit 1-4 sisi kedua (bolak-balik), format sama color_set_1');
            $table->enum('flap_side', ['TOP', 'BOTTOM', 'LEFT', 'RIGHT'])->nullable()->comment('Sisi tempat lidah/flap menempel pada body part');
            $table->decimal('flap_length_mm', 8, 2)->nullable()->comment('Nilai desain lidah saat ini');
            $table->decimal('flap_min_mm', 8, 2)->nullable()->comment('Batas bawah rentang valid struktural -- default dari mat_flap_rule, boleh override per part');
            $table->decimal('flap_max_mm', 8, 2)->nullable()->comment('Batas atas rentang valid struktural -- default dari mat_flap_rule, boleh override per part');
            $table->decimal('flap_width_mm', 8, 2)->nullable()->comment('Lebar lidah (Tingkat 2 interlock, model badan persegi + lidah persegi kecil)');
            $table->decimal('flap_offset_mm', 8, 2)->nullable()->comment('Offset lidah dari tepi body (Tingkat 2 interlock)');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_part', function (Blueprint $table) {
            $table->dropColumn([
                'mastercard_code', 'die_code', 'die_w_cm', 'die_h_cm',
                'sheet_raw_w_cm', 'sheet_raw_h_cm', 'color_set_1', 'color_set_2',
                'flap_side', 'flap_length_mm', 'flap_min_mm', 'flap_max_mm',
                'flap_width_mm', 'flap_offset_mm',
            ]);
        });
    }
};
