<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paritas field dgn program HPP lama (docs/ddl/tmhpp.sql hpp_parts + map_hpp_parts,
// screenshot "UPDATE HPP V3.027", mockup Kalkulasi_HPP_UAT.html) -- semua parameter
// manual yang ada di layar lama WAJIB ada (keputusan user 2026-09-18). Nama kolom
// mengikuti legacy supaya rumus di procost_formula_library.md §VII bisa dibaca 1:1.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing', function (Blueprint $table) {
            $table->string('customer_name_draft', 255)->nullable()->after('customer_id')
                ->comment('Legacy hpp.customer_name: nama calon customer / lead (bila belum jadi customer master)');
        });

        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table) {
            $table->boolean('pakai_laminasi')->default(false)->after('data_source')
                ->comment('Legacy F_sheet/B36: pengali J36 (lam sheet flat) & komponen SH di J38');
            $table->decimal('i_uk_cetak_p_cm', 12, 4)->nullable()->after('i_lebar_plano_cm')
                ->comment('Ukuran cetak P (cm), Excel B22. Luas cetak = P x L (BUKAN luas plano) utk coating/film/SF EF/J38');
            $table->decimal('i_uk_cetak_l_cm', 12, 4)->nullable()->after('i_uk_cetak_p_cm')
                ->comment('Ukuran cetak L (cm), Excel C22');
            $table->decimal('i_paper_price_override', 18, 4)->nullable()->after('grammage_id')
                ->comment('Override harga kertas (Rp/rim duplex atau Rp/kg ivory/kraft) -- "Paper Index" mode Integrasi. NULL = master');
            $table->decimal('i_ongkos_hotprint_pc', 18, 4)->default(0)->after('i_ongkos_lem_pc');
            $table->decimal('i_lam_sheet_flat_pc', 18, 4)->default(0)
                ->comment('J36 laminasi sheet: nilai AKHIR Rp/PC (NS40=1360)');
            $table->decimal('i_ongkos_lam_rp_cm2_sh', 10, 4)->nullable()->comment('J38 faktor lam sheet Rp/cm2 (NS40=0.15)');
            $table->decimal('i_ongkos_lam_rp_cm2_sf', 10, 4)->nullable()->comment('J38 faktor lam SF Rp/cm2 (RAJA BAKERY=0.065)');
            $table->boolean('i_lam_pakai_ukuran_sf')->default(false)->comment('J38: 1=pakai ukuran piece SF/SH, 0=ukuran cetak');
            $table->decimal('i_ongkos_laminasi_pc', 18, 4)->default(0)->comment('J38 fallback manual bila faktor sh & sf kosong');
            $table->unsignedBigInteger('sf_ef_rate_id')->nullable()->comment('LOGICAL -> tm2mst.mat_sheet_rate (flute E/F, subtype SF)');
            $table->decimal('i_sheet_panjang_cm', 12, 4)->nullable()->comment('Panjang piece SF/SH (cm), Excel B23');
            $table->decimal('i_sheet_lebar_cm', 12, 4)->nullable()->comment('Lebar piece SF (cm), Excel C23');
            $table->decimal('i_lebar_trim_cm', 12, 4)->nullable()->comment('SHEET manual: lebar trim (cm). NULL = diturunkan dari geometri');
            $table->unsignedSmallInteger('i_isi_per_trim')->nullable()->comment('SHEET manual: isi/trim. NULL = diturunkan');
            $table->decimal('i_discount_rm', 8, 4)->nullable()->comment('Diskon(+)/surcharge(-) sheet manual, batas OB_DISCOUNT_RM_MIN/MAX. NULL = tier');
            $table->string('packing_type_code', 20)->nullable()->comment('LOGICAL -> tm2mst.mat_packing_type.code');
            $table->string('join_type_code', 30)->nullable()->comment('LOGICAL -> tm2mst.mat_join_type.code (SHEET)');
            $table->decimal('i_ongkos_pisau_pc', 18, 4)->default(0)->comment('SHEET: ongkos pisau diecut per PC (bukan tooling DC)');
            $table->decimal('c_ongkos_lam_pc', 18, 4)->nullable()->comment('J38 hasil');
            $table->text('c_sheet_compare_info')->nullable()->comment('Info Harga Supplier (compare semua supplier aktif)');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table) {
            $table->dropColumn([
                'pakai_laminasi', 'i_uk_cetak_p_cm', 'i_uk_cetak_l_cm', 'i_paper_price_override', 'i_ongkos_hotprint_pc',
                'i_lam_sheet_flat_pc', 'i_ongkos_lam_rp_cm2_sh', 'i_ongkos_lam_rp_cm2_sf', 'i_lam_pakai_ukuran_sf',
                'i_ongkos_laminasi_pc', 'sf_ef_rate_id', 'i_sheet_panjang_cm', 'i_sheet_lebar_cm', 'i_lebar_trim_cm',
                'i_isi_per_trim', 'i_discount_rm', 'packing_type_code', 'join_type_code', 'i_ongkos_pisau_pc',
                'c_ongkos_lam_pc', 'c_sheet_compare_info',
            ]);
        });

        Schema::connection('tm2sdi')->table('iqp_costing', function (Blueprint $table) {
            $table->dropColumn('customer_name_draft');
        });
    }
};
