<?php

/*
 * REMARK
 * Tujuan: fondasi ENGINE+ML saran ProCost (Jo 2026-10-16): (1) master template tarif part (impor tmhpp.m_part_template),
 *         (2) konstanta default margin 18% + ambang statistik/ML, (3) tabel saran + catatan belajar per isian.
 * Sumber hulu: docs/ddl/tmhpp.sql (m_part_template, 6 baris); sys_constant COSTING.
 * Input: tidak ada (seed tetap). Output: tm2mst.prod_part_template, tm2sys.sys_constant, tm2sdi.iqp_costing_recommendation.
 * Dipakai oleh: App\Services\Costing\CostingAdvisor, pages::inquiry.costing.
 * Status: BARU 2026-10-16; template hanya dipakai untuk TARIF (geometri produk tidak disalin, itu milik Inquiry/ProDev).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! Schema::connection('tm2mst')->hasTable('prod_part_template')) {
            Schema::connection('tm2mst')->create('prod_part_template', function (Blueprint $t) {
                $t->id('id_part_template');
                $t->string('template_code', 40)->unique();
                $t->string('template_name', 120);
                $t->string('part_label', 40)->nullable();
                $t->string('part_type_code', 20)->index()->comment('DC | DC+SF | DC+SHEET | KRAFT | SHEET | SHEET_DIGITAL');
                $t->json('defaults')->comment('Nilai awal per kolom iqp_costing_part (legacy m_part_template). Advisor hanya memakai kolom TARIF, bukan geometri.');
                $t->boolean('activate')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
            });
            $rows = json_decode(<<<'JSON'
[{"template_code":"NS40","template_name":"NS40 DC 200X134X247MM","part_label":"Bag Atas","part_type_code":"DC+SHEET","defaults":{"i_dc_persen_override":5.3,"i_panjang_plano_cm":70,"i_lebar_plano_cm":100,"i_cut_per_plano":2,"i_isi_per_lb":1,"i_uk_cetak_p_cm":70,"i_uk_cetak_l_cm":41,"i_jumlah_warna":4,"i_film_rate":55,"i_film_faktor":0.25,"i_ongkos_plate_warna":90000,"i_pisau_faktor":0.25,"i_pisau_rate_rp":900000,"i_ongkos_cetak_rim":900000,"i_ongkos_plong_lb":250,"i_ongkos_lem_pc":375,"i_ongkos_kupas_pc":30,"i_ongkos_qc_pack_pc":100,"i_ongkos_kirim_pc":200,"i_ongkos_hotprint_pc":0,"i_ongkos_print_pc":0,"i_ongkos_diecut_pc":0,"i_ongkos_slitter_pc":0,"i_ongkos_slotter_pc":0,"i_ongkos_join_pc":0,"i_ongkos_partisi_pc":0,"i_ongkos_layer_pc":0,"i_ongkos_pisau_pc":0,"i_ongkos_laminasi_pc":430.5,"i_lam_sheet_flat_pc":1360,"i_ongkos_lam_rp_cm2_sh":0.15,"i_ongkos_lam_rp_cm2_sf":0.1,"i_sheet_panjang_cm":70,"i_sheet_lebar_cm":39.5,"i_lebar_trim_cm":160,"i_isi_per_trim":4,"i_discount_rm":-0.1,"supplier_sheet_code":"UJK"}},{"template_code":"NKC135-BADAN","template_name":"NKC135 DC 509X186X255MM - Bag Badan","part_label":"Bag Atas","part_type_code":"DC+SHEET","defaults":{"i_dc_persen_override":5,"i_panjang_plano_cm":79,"i_lebar_plano_cm":48,"i_cut_per_plano":1,"i_isi_per_lb":1,"i_uk_cetak_p_cm":75,"i_uk_cetak_l_cm":46.5,"i_jumlah_warna":4,"i_film_rate":55,"i_film_faktor":0.25,"i_ongkos_plate_warna":90000,"i_pisau_faktor":0.25,"i_pisau_rate_rp":900000,"i_ongkos_cetak_rim":400000,"i_ongkos_plong_lb":200,"i_ongkos_lem_pc":650,"i_ongkos_kupas_pc":400,"i_ongkos_qc_pack_pc":100,"i_ongkos_kirim_pc":300,"i_ongkos_hotprint_pc":700,"i_ongkos_print_pc":0,"i_ongkos_diecut_pc":0,"i_ongkos_slitter_pc":0,"i_ongkos_slotter_pc":0,"i_ongkos_join_pc":0,"i_ongkos_partisi_pc":0,"i_ongkos_layer_pc":0,"i_ongkos_pisau_pc":0,"i_ongkos_laminasi_pc":505.05,"i_lam_sheet_flat_pc":1360,"i_ongkos_lam_rp_cm2_sh":0,"i_ongkos_lam_rp_cm2_sf":0,"i_sheet_panjang_cm":74,"i_sheet_lebar_cm":45.5,"i_lebar_trim_cm":140,"i_isi_per_trim":3,"i_discount_rm":-0.05,"supplier_sheet_code":"UJK"}},{"template_code":"NKC135-TUTUP","template_name":"NKC135 DC 509X186X255MM - Bag Tutup","part_label":"Bag Bawah","part_type_code":"DC+SHEET","defaults":{"i_dc_persen_override":5,"i_panjang_plano_cm":79,"i_lebar_plano_cm":61,"i_cut_per_plano":1,"i_isi_per_lb":1,"i_uk_cetak_p_cm":75,"i_uk_cetak_l_cm":59,"i_jumlah_warna":4,"i_film_rate":55,"i_film_faktor":0.25,"i_ongkos_plate_warna":90000,"i_pisau_faktor":0.25,"i_pisau_rate_rp":900000,"i_ongkos_cetak_rim":400000,"i_ongkos_plong_lb":200,"i_ongkos_lem_pc":0,"i_ongkos_kupas_pc":0,"i_ongkos_qc_pack_pc":0,"i_ongkos_kirim_pc":0,"i_ongkos_hotprint_pc":0,"i_ongkos_print_pc":0,"i_ongkos_diecut_pc":0,"i_ongkos_slitter_pc":0,"i_ongkos_slotter_pc":0,"i_ongkos_join_pc":0,"i_ongkos_partisi_pc":0,"i_ongkos_layer_pc":0,"i_ongkos_pisau_pc":0,"i_ongkos_laminasi_pc":309.75,"i_lam_sheet_flat_pc":1360,"i_ongkos_lam_rp_cm2_sh":0,"i_ongkos_lam_rp_cm2_sf":0,"i_sheet_panjang_cm":74,"i_sheet_lebar_cm":0,"i_lebar_trim_cm":182,"i_isi_per_trim":3,"i_discount_rm":-0.05,"supplier_sheet_code":"UJK"}},{"template_code":"OB_A1","template_name":"OB A1 240X260X280MM","part_label":"Outer Box","part_type_code":"SHEET","defaults":{"i_dc_persen_override":0,"i_panjang_plano_cm":0,"i_lebar_plano_cm":0,"i_cut_per_plano":1,"i_isi_per_lb":1,"i_uk_cetak_p_cm":0,"i_uk_cetak_l_cm":0,"i_jumlah_warna":0,"i_film_faktor":0,"i_ongkos_plate_warna":0,"i_pisau_faktor":0,"i_pisau_rate_rp":0,"i_ongkos_cetak_rim":0,"i_ongkos_plong_lb":0,"i_ongkos_lem_pc":0,"i_ongkos_kupas_pc":0,"i_ongkos_qc_pack_pc":100,"i_ongkos_kirim_pc":100,"i_ongkos_hotprint_pc":0,"i_ongkos_print_pc":300,"i_ongkos_diecut_pc":350,"i_ongkos_slitter_pc":0,"i_ongkos_slotter_pc":0,"i_ongkos_join_pc":0,"i_ongkos_partisi_pc":0,"i_ongkos_layer_pc":0,"i_ongkos_pisau_pc":0,"i_ongkos_laminasi_pc":0,"i_lam_sheet_flat_pc":0,"i_ongkos_lam_rp_cm2_sh":0,"i_ongkos_lam_rp_cm2_sf":0,"i_sheet_panjang_cm":126.9,"i_sheet_lebar_cm":50.2,"i_lebar_trim_cm":155,"i_isi_per_trim":3,"i_discount_rm":0.035,"supplier_sheet_code":"UJK"}},{"template_code":"KALENDER","template_name":"KALENDER MAJOIN 46X64","part_label":"Bag Atas","part_type_code":"DC","defaults":{"i_dc_persen_override":0,"i_panjang_plano_cm":65,"i_lebar_plano_cm":100,"i_cut_per_plano":1,"i_isi_per_lb":1,"i_uk_cetak_p_cm":65,"i_uk_cetak_l_cm":100,"i_jumlah_warna":4,"i_film_faktor":0,"i_ongkos_plate_warna":90000,"i_pisau_faktor":0,"i_pisau_rate_rp":0,"i_ongkos_cetak_rim":750000,"i_ongkos_plong_lb":150,"i_ongkos_lem_pc":0,"i_ongkos_kupas_pc":0,"i_ongkos_qc_pack_pc":50,"i_ongkos_kirim_pc":50,"i_ongkos_hotprint_pc":0,"i_ongkos_print_pc":0,"i_ongkos_diecut_pc":0,"i_ongkos_slitter_pc":0,"i_ongkos_slotter_pc":0,"i_ongkos_join_pc":0,"i_ongkos_partisi_pc":0,"i_ongkos_layer_pc":0,"i_ongkos_pisau_pc":0,"i_ongkos_laminasi_pc":0,"i_lam_sheet_flat_pc":0,"i_ongkos_lam_rp_cm2_sh":0,"i_ongkos_lam_rp_cm2_sf":0,"i_sheet_panjang_cm":0,"i_sheet_lebar_cm":0,"i_lebar_trim_cm":0,"i_isi_per_trim":1,"i_discount_rm":0,"supplier_sheet_code":"UJK"}},{"template_code":"FS GTZ-5S","template_name":"FS GTZ-5S MF TRIAL","part_label":"Bag Atas","part_type_code":"DC+SF","defaults":{"i_dc_persen_override":0,"i_panjang_plano_cm":89,"i_lebar_plano_cm":125,"i_cut_per_plano":3,"i_isi_per_lb":4,"i_uk_cetak_p_cm":89,"i_uk_cetak_l_cm":42,"i_jumlah_warna":4,"i_film_rate":55,"i_film_faktor":0.05,"i_ongkos_plate_warna":90000,"i_pisau_faktor":0.05,"i_pisau_rate_rp":900000,"i_ongkos_cetak_rim":1500000,"i_ongkos_plong_lb":250,"i_ongkos_lem_pc":60,"i_ongkos_kupas_pc":20,"i_ongkos_qc_pack_pc":20,"i_ongkos_kirim_pc":20,"i_ongkos_hotprint_pc":0,"i_ongkos_print_pc":0,"i_ongkos_diecut_pc":0,"i_ongkos_slitter_pc":0,"i_ongkos_slotter_pc":0,"i_ongkos_join_pc":0,"i_ongkos_partisi_pc":0,"i_ongkos_layer_pc":0,"i_ongkos_pisau_pc":0,"i_ongkos_laminasi_pc":0,"i_lam_sheet_flat_pc":0,"i_ongkos_lam_rp_cm2_sh":0.075,"i_ongkos_lam_rp_cm2_sf":0.065,"i_sheet_panjang_cm":88.5,"i_sheet_lebar_cm":41,"i_lebar_trim_cm":170,"i_isi_per_trim":4,"i_discount_rm":-0.05,"supplier_sheet_code":"UJK"}}]
JSON, true);
            foreach ($rows as $r) {
                DB::connection('tm2mst')->table('prod_part_template')->insert([
                    'template_code' => $r['template_code'], 'template_name' => $r['template_name'], 'part_label' => $r['part_label'],
                    'part_type_code' => $r['part_type_code'], 'defaults' => json_encode($r['defaults']),
                    'activate' => true, 'created_by' => 'legacy_tmhpp', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach ([
            ['MARGIN_DEFAULT_PCT', 18, 'Margin default (%) yang diisi ProCost bila officer belum mengubah. Jo 2026-10-16: 18 untuk semua, berubah bertahap lewat pembelajaran'],
            ['ADVISOR_MIN_SAMPLE_STAT', 5, 'Jumlah costing serupa minimum agar saran ProCost memakai statistik (median); di bawahnya pakai default'],
            ['ADVISOR_MIN_SAMPLE_ML', 30, 'Jumlah costing serupa minimum agar saran ProCost memakai machine learning ringan (dipakai bila lebih akurat dari median)'],
        ] as [$key, $num, $desc]) {
            DB::connection('tm2sys')->table('sys_constant')->updateOrInsert(['constant_key' => $key], [
                'category_code' => 'PRODUCTION', 'constant_group' => 'COSTING', 'constant_number' => $num, 'description' => $desc,
                'activate' => true, 'created_by' => 'procost_advisor', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! Schema::connection('tm2sdi')->hasTable('iqp_costing_recommendation')) {
            Schema::connection('tm2sdi')->create('iqp_costing_recommendation', function (Blueprint $t) {
                $t->id('id_costing_recommendation');
                $t->unsignedBigInteger('costing_id')->index();
                $t->unsignedBigInteger('costing_part_id')->nullable()->comment('NULL untuk isian tier/header');
                $t->unsignedBigInteger('tier_id')->nullable()->comment('iqp_costing_price_tier.id_price_tier untuk isian tier');
                $t->string('field_key', 40)->comment('nama kolom iqp_costing_part / price_tier / costing');
                $t->decimal('recommended_value', 18, 4)->nullable();
                $t->decimal('final_value', 18, 4)->nullable()->comment('nilai akhir saat Hitung (bahan belajar)');
                $t->string('source', 10)->comment('INQUIRY | PRODEV | TEMPLATE | CONSTANT | MASTER | ESTIMATE | STAT | ML');
                $t->string('method', 8)->comment('DEFAULT | DERIVED | STAT | ML');
                $t->string('source_note', 200)->nullable();
                $t->unsignedInteger('sample_count')->default(0);
                $t->boolean('is_override')->default(false)->comment('officer mengubah nilai saran');
                $t->boolean('zero_intent')->default(false)->comment('officer sengaja mengosongkan (0), jangan dipulihkan');
                $t->string('part_type_code', 20)->nullable()->index();
                $t->decimal('qty_pcs', 18, 2)->nullable();
                $t->timestamp('learned_at')->nullable()->index();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->index(['field_key', 'part_type_code', 'learned_at'], 'ix_costing_rec_learn');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->dropIfExists('iqp_costing_recommendation');
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['MARGIN_DEFAULT_PCT', 'ADVISOR_MIN_SAMPLE_STAT', 'ADVISOR_MIN_SAMPLE_ML'])->delete();
        Schema::connection('tm2mst')->dropIfExists('prod_part_template');
    }
};
