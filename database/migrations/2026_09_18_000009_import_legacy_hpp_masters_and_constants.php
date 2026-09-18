<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Impor master & konstanta program HPP lama (docs/ddl/tmhpp.sql, sumber kebenaran,
// procost_formula_library.md §VII) -- master tm2mst sebelumnya masih dummy (coating
// WB 0.85/UV 1.35 vs legacy 0.035/0.020, pisau 150rb vs 900rb, gramatur 3 baris
// harga karangan). Baris lama TIDAK dihapus (dipakai costing existing via id),
// cuma dinonaktifkan + ditandai DUMMY.
return new class extends Migration
{
    private function originCols(Blueprint $t): void
    {
        $t->boolean('activate')->default(true);
        $t->timestamps();
        $t->string('created_by', 100)->nullable();
        $t->string('updated_by', 100)->nullable();
        $t->string('data_origin', 10)->default('LIVE')->index();
        $t->boolean('data_ready')->default(true);
        $t->boolean('data_origin_manual')->default(false);
    }

    public function up(): void
    {
        $mst = DB::connection('tm2mst');
        $now = now();

        // ── Master baru (dulu belum ada di V2) ──
        Schema::connection('tm2mst')->create('mat_film_rate', function (Blueprint $t) {
            $t->id('id_film_rate');
            $t->decimal('rate_value', 10, 2)->unique()->comment('Nilai film rate (55/60/45/30) -- legacy m_film_rate.code');
            $t->string('label', 60);
            $t->unsignedTinyInteger('sort_order')->default(1);
            $this->originCols($t);
        });
        Schema::connection('tm2mst')->create('mat_join_type', function (Blueprint $t) {
            $t->id('id_join_type');
            $t->string('code', 30)->unique();
            $t->string('label', 100);
            $t->decimal('default_rate', 18, 4)->default(0)->comment('Default ongkos join Rp/PC, overridable per part');
            $t->unsignedTinyInteger('sort_order')->default(1);
            $this->originCols($t);
        });
        Schema::connection('tm2mst')->create('mat_packing_type', function (Blueprint $t) {
            $t->id('id_packing_type');
            $t->string('code', 20)->unique();
            $t->string('label', 100);
            $t->string('uom', 10)->default('PC');
            $t->decimal('default_rate', 18, 4)->default(0);
            $t->unsignedTinyInteger('sort_order')->default(1);
            $this->originCols($t);
        });
        Schema::connection('tm2mst')->create('mat_sheet_supplier', function (Blueprint $t) {
            $t->id('id_sheet_supplier');
            $t->string('code', 20)->unique();
            $t->string('name', 100);
            $t->unsignedTinyInteger('sort_order')->default(1);
            $t->string('notes', 255)->nullable()->comment('Lebar mesin TIDAK disimpan di sini -- pakai konstanta SHEET_MACHINE_WIDTH_CM (keputusan user 2026-09-18)');
            $this->originCols($t);
        });

        foreach ([[30, '30 — Bag Bawah / Tutup Tipis', 4], [45, '45 — Ivory / Kertas Biasa', 3], [55, '55 — Normal', 1], [60, '60 — Full Cetak + WB Lam', 2]] as [$v, $l, $s]) {
            $mst->table('mat_film_rate')->insert(['rate_value' => $v, 'label' => $l, 'sort_order' => $s, 'created_by' => 'legacy_tmhpp', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([['STITCHING_SINGLE', 'Stitching Single — kawat 1 lapis', 375, 1], ['STITCHING_DOUBLE', 'Stitching Double — kawat 2 lapis', 750, 2], ['GLUE_SINGLE', 'Glue Single — lem 1 lapis', 250, 3], ['GLUE_DOUBLE', 'Glue Double — lem 2 lapis', 500, 4]] as [$c, $l, $r, $s]) {
            $mst->table('mat_join_type')->insert(['code' => $c, 'label' => $l, 'default_rate' => $r, 'sort_order' => $s, 'created_by' => 'legacy_tmhpp', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([['NONE', 'Tanpa packing tambahan', 1], ['WRAP', 'Wrapping (plastik / OPP)', 2], ['SHRINK', 'Shrink film', 3], ['WRAP_SHRINK', 'Wrap + Shrink kombinasi', 4]] as [$c, $l, $s]) {
            $mst->table('mat_packing_type')->insert(['code' => $c, 'label' => $l, 'default_rate' => 0, 'sort_order' => $s, 'created_by' => 'legacy_tmhpp', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([['UJK', 'UJK', 1, 'Disc: flat surcharge per substance'], ['RCA', 'RCA', 2, 'Disc: tiered by running meter']] as [$c, $n, $s, $note]) {
            $mst->table('mat_sheet_supplier')->insert(['code' => $c, 'name' => $n, 'sort_order' => $s, 'notes' => $note, 'created_by' => 'legacy_tmhpp', 'created_at' => $now, 'updated_at' => $now]);
        }

        // ── Gramatur (m_paper_grammage, 28 baris). DC% legacy disimpan x100 (530) -> 5.30;
        // faktor engine = 1 + 5.30 = 6.30 (§VII). Versi terbaru per (tipe,gsm) = aktif. ──
        $mst->table('mat_grammage')->update(['activate' => false, 'data_origin' => 'DUMMY', 'updated_at' => $now]);
        $grammage = [
            ['IVORY', 85, null, null, null, '2000-01-01'], ['IVORY', 100, null, null, null, '2000-01-01'],
            ['IVORY', 120, 15500.00, null, null, '2000-01-01'], ['IVORY', 150, null, null, null, '2000-01-01'],
            ['IVORY', 200, null, null, null, '2000-01-01'], ['IVORY', 210, null, null, null, '2000-01-01'],
            ['IVORY', 230, null, null, null, '2000-01-01'],
            ['DUPLEX', 250, null, 255000, 530, '2026-03-01'], ['DUPLEX', 270, null, 267000, 530, '2026-03-01'],
            ['DUPLEX', 310, null, 290000, 530, '2026-03-01'], ['DUPLEX', 350, null, 314000, 530, '2026-03-01'],
            ['DUPLEX', 400, null, 353000, 530, '2026-03-01'], ['DUPLEX', 450, null, 395000, 530, '2026-03-01'],
            ['DUPLEX', 500, null, 439000, 530, '2026-03-01'],
            ['KRAFT', 85, null, null, null, '2000-01-01'], ['KRAFT', 100, null, null, null, '2000-01-01'],
            ['KRAFT', 120, null, null, null, '2000-01-01'], ['KRAFT', 150, null, null, null, '2000-01-01'],
            ['KRAFT', 200, null, null, null, '2000-01-01'],
            ['DUPLEX', 250, null, 253000, 530, '2026-04-01'], ['DUPLEX', 270, null, 267000, 530, '2026-04-01'],
            ['DUPLEX', 310, null, 287000, 530, '2026-04-01'], ['DUPLEX', 350, null, 311000, 530, '2026-04-01'],
            ['DUPLEX', 400, null, 350000, 530, '2026-04-01'], ['DUPLEX', 450, null, 395000, 530, '2026-04-01'],
            ['DUPLEX', 500, null, 435000, 530, '2026-04-01'],
            ['KRAFT', 250, 6700.00, null, null, '2025-08-01'], ['KRAFT', 250, 8350.00, null, null, '2026-04-01'],
        ];
        $latest = [];
        foreach ($grammage as $g) {
            $k = $g[0].'|'.$g[1];
            $latest[$k] = max($latest[$k] ?? '0000-00-00', $g[5]);
        }
        foreach ($grammage as [$type, $gsm, $kg, $duplex, $dcX100, $date]) {
            $mst->table('mat_grammage')->insert([
                'grammage_name' => "{$type} {$gsm} gsm",
                'grammage_value' => $gsm,
                'paper_kind' => $type,
                'duplex_price_list' => $duplex ?? 0,
                'duplex_dc_pct' => $dcX100 !== null ? $dcX100 / 100 : 0,
                'ivory_ap_price_per_kg' => $kg ?? 0,
                'effective_date' => $date,
                'activate' => $latest["{$type}|{$gsm}"] === $date,
                'created_by' => 'legacy_tmhpp',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── Coating (m_coating_rate legacy, index Rp/cm²) ──
        $mst->table('mat_coating_rate')->update(['activate' => false, 'data_origin' => 'DUMMY', 'updated_at' => $now]);
        foreach ([['Varnish', 0.035], ['Water Base', 0.035], ['UV', 0.020], ['UV-Spot', 0.025], ['Laminating Biasa', 0.165], ['Laminating Glossy', 0.165], ['Laminating Doff', 0.210], ['Laminating 3D', 0.210], ['Other', 0], ['No Coating', 0]] as [$type, $idx]) {
            $mst->table('mat_coating_rate')->insert(['coating_type' => $type, 'index_coating' => $idx, 'activate' => true, 'created_by' => 'legacy_tmhpp', 'created_at' => $now, 'updated_at' => $now]);
        }

        // ── Sheet rate (m_sheet_rate 107 baris, + subtype SHEET/SINGLE FACE/SF) ──
        Schema::connection('tm2mst')->table('mat_sheet_rate', function (Blueprint $t) {
            $t->string('mat_subtype', 30)->nullable()->after('index_substance')->comment('SHEET | SINGLE FACE | SF (E/F, dipakai SF EF)');
        });
        foreach ($this->legacySheetRates() as [$substance, $flute, $index, $subtype]) {
            $exists = $mst->table('mat_sheet_rate')->where('substance_name', $substance)->where('flute_type', $flute)->exists();
            $row = ['index_substance' => $index, 'mat_subtype' => $subtype, 'activate' => true, 'updated_at' => $now, 'updated_by' => 'legacy_tmhpp'];
            if ($exists) {
                $mst->table('mat_sheet_rate')->where('substance_name', $substance)->where('flute_type', $flute)->update($row);
            } else {
                $mst->table('mat_sheet_rate')->insert($row + ['substance_name' => $substance, 'flute_type' => $flute, 'created_by' => 'legacy_tmhpp', 'created_at' => $now]);
            }
        }

        // ── Tier diskon RCA (dipakai "Info Harga Supplier"). Tier UJK TIDAK diubah --
        // SHEET_DIGITAL sudah diverifikasi byte-presisi ke Excel dgn tier UJK sekarang. ──
        foreach ([[0, 300, 0.20], [300, 500, 0.20], [500, 1000, 0.25], [1000, null, 0.26]] as [$from, $to, $disc]) {
            $mst->table('mat_sheet_discount_tier')->insert(['supplier_code' => 'RCA', 'rm_from' => $from, 'rm_to' => $to, 'discount_pct' => $disc, 'activate' => true, 'created_by' => 'legacy_tmhpp', 'created_at' => $now, 'updated_at' => $now]);
        }

        // ── Tipe part: label & rate pisau legacy (m_part_type) ──
        foreach ([['DC', 'DC — Duplex Carton', 900000], ['DC+SF', 'DC + Single Face', 900000], ['DC+SHEET', 'DC + Laminasi Sheet', 900000], ['KRAFT', 'KRAFT — Kraft Box', 1500000], ['SHEET', 'SHEET — Outer Box/OB', 0], ['SHEET_DIGITAL', 'SHEET DIGITAL — OB Digital', 0]] as [$code, $label, $pisau]) {
            $mst->table('prod_part_type')->where('part_type_code', $code)->update(['part_type_label' => $label, 'pisau_rate_rp' => $pisau, 'updated_at' => $now]);
        }

        // ── Konstanta (m_hpp_constants) yang belum ada di sys_constant ──
        $sys = DB::connection('tm2sys');
        foreach ([
            ['KNIFE_FACTOR_DEFAULT', 0.25, null, 'Faktor amortisasi pisau default (legacy m_hpp_constants)'],
            ['FILM_RATE_FULLPRINT', 60, null, 'Film rate cetak penuh + WB laminasi'],
            ['OB_DISCOUNT_RM_MIN', -0.99, null, 'Batas bawah diskon/surcharge sheet (-0.99 = surcharge maks 99%)'],
            ['OB_DISCOUNT_RM_MAX', 0.99, null, 'Batas atas diskon/surcharge sheet (0.99 = diskon maks 99%)'],
            ['PROCOST_FORMULA_LOGINS', null, 'johiwan,wiwik2', 'Login (dipisah koma) yang boleh melihat breakdown rumus & HPP ProCost. Carrij Suhendra: tambahkan login-nya di sini setelah akun dibuat'],
        ] as [$key, $num, $text, $desc]) {
            $sys->table('sys_constant')->updateOrInsert(['constant_key' => $key], [
                'category_code' => 'PRODUCTION', 'constant_group' => 'COSTING',
                'constant_number' => $num, 'constant_text' => $text, 'description' => $desc,
                'activate' => true, 'created_by' => 'legacy_tmhpp', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['KNIFE_FACTOR_DEFAULT', 'FILM_RATE_FULLPRINT', 'OB_DISCOUNT_RM_MIN', 'OB_DISCOUNT_RM_MAX', 'PROCOST_FORMULA_LOGINS'])->delete();
        $mst->table('mat_sheet_discount_tier')->where('created_by', 'legacy_tmhpp')->delete();
        $mst->table('mat_sheet_rate')->where('created_by', 'legacy_tmhpp')->delete();
        Schema::connection('tm2mst')->table('mat_sheet_rate', fn (Blueprint $t) => $t->dropColumn('mat_subtype'));
        $mst->table('mat_coating_rate')->where('created_by', 'legacy_tmhpp')->delete();
        $mst->table('mat_coating_rate')->update(['activate' => true, 'data_origin' => 'LIVE']);
        $mst->table('mat_grammage')->where('created_by', 'legacy_tmhpp')->delete();
        $mst->table('mat_grammage')->update(['activate' => true, 'data_origin' => 'LIVE']);
        foreach (['mat_sheet_supplier', 'mat_packing_type', 'mat_join_type', 'mat_film_rate'] as $t) {
            Schema::connection('tm2mst')->dropIfExists($t);
        }
    }

    private function legacySheetRates(): array
    {
        return [
            ['M125/M125/M125', 'B/F', 4283, 'SHEET'], ['K125/M125/M125', 'B/F', 4410, 'SHEET'], ['K125/M125/K125', 'B/F', 4537, 'SHEET'],
            ['K150/M125/M125', 'B/F', 4631, 'SHEET'], ['K150/M125/K125', 'B/F', 4758, 'SHEET'], ['K150/M125/K150', 'B/F', 4979, 'SHEET'],
            ['K200/M125/M125', 'B/F', 5268, 'SHEET'], ['K200/M125/K125', 'B/F', 5400, 'SHEET'], ['K200/M125/K150', 'B/F', 5629, 'SHEET'],
            ['K200/M125/K200', 'B/F', 6088, 'SHEET'], ['K275/M125/M125', 'B/F', 6528, 'SHEET'], ['K275/M125/K125', 'B/F', 6666, 'SHEET'],
            ['K275/M125/K150', 'B/F', 6903, 'SHEET'], ['K275/M125/K200', 'B/F', 7379, 'SHEET'], ['K275/M125/K275', 'B/F', 8746, 'SHEET'],
            ['WK150/M125/M125', 'B/F', 5153, 'SHEET'], ['WK150/M125/K125', 'B/F', 5285, 'SHEET'], ['WK150/M125/K150', 'B/F', 5515, 'SHEET'],
            ['WK150/M125/K200', 'B/F', 5973, 'SHEET'], ['WK150/M125/K275', 'B/F', 7260, 'SHEET'], ['WK150/M125/WK150', 'B/F', 6293, 'SHEET'],
            ['WK200/M125/M125', 'B/F', 5726, 'SHEET'], ['WK200/M125/K125', 'B/F', 5859, 'SHEET'], ['WK200/M125/K150', 'B/F', 6088, 'SHEET'],
            ['WK200/M125/K200', 'B/F', 6547, 'SHEET'], ['WK200/M125/K275', 'B/F', 7855, 'SHEET'], ['WK200/M125/WK150', 'B/F', 6908, 'SHEET'],
            ['WK200/M125/WK200', 'B/F', 7524, 'SHEET'], ['K150/M150/K150', 'B/F', 5279, 'SHEET'], ['K200/M150/K150', 'B/F', 5929, 'SHEET'],
            ['K200/M150/K200', 'B/F', 6388, 'SHEET'], ['WK150/M150/K150', 'B/F', 5815, 'SHEET'], ['K275/M150/K275', 'B/F', 9046, 'SHEET'],
            ['S FACE 125 M / 125 M', 'B/F', 2850, 'SINGLE FACE'], ['S FACE 125 M / 125 K', 'B/F', 2950, 'SINGLE FACE'],
            ['S FACE 125 M / 150 K', 'B/F', 3200, 'SINGLE FACE'], ['S FACE 125 M / 150 WK', 'B/F', 3450, 'SINGLE FACE'],
            ['S FACE 150 M / 150 K', 'B/F', 3550, 'SINGLE FACE'],
            ['M125/M125/M125', 'C/F', 4380, 'SHEET'], ['K125/M125/M125', 'C/F', 4508, 'SHEET'], ['K125/M125/K125', 'C/F', 4635, 'SHEET'],
            ['K150/M125/M125', 'C/F', 4729, 'SHEET'], ['K150/M125/K125', 'C/F', 4856, 'SHEET'], ['K150/M125/K150', 'C/F', 5077, 'SHEET'],
            ['K200/M125/M125', 'C/F', 5369, 'SHEET'], ['K200/M125/K125', 'C/F', 5501, 'SHEET'], ['K200/M125/K150', 'C/F', 5731, 'SHEET'],
            ['K200/M125/K200', 'C/F', 6189, 'SHEET'], ['K275/M125/M125', 'C/F', 6634, 'SHEET'], ['K275/M125/K125', 'C/F', 6771, 'SHEET'],
            ['K275/M125/K150', 'C/F', 7009, 'SHEET'], ['K275/M125/K200', 'C/F', 7484, 'SHEET'], ['K275/M125/K275', 'C/F', 8855, 'SHEET'],
            ['WK150/M125/M125', 'C/F', 5254, 'SHEET'], ['WK150/M125/K125', 'C/F', 5387, 'SHEET'], ['WK150/M125/K150', 'C/F', 5616, 'SHEET'],
            ['WK150/M125/K200', 'C/F', 6075, 'SHEET'], ['WK150/M125/K275', 'C/F', 7365, 'SHEET'], ['WK150/M125/WK150', 'C/F', 6402, 'SHEET'],
            ['WK200/M125/M125', 'C/F', 5828, 'SHEET'], ['WK200/M125/K125', 'C/F', 5960, 'SHEET'], ['WK200/M125/K150', 'C/F', 6189, 'SHEET'],
            ['WK200/M125/K200', 'C/F', 6648, 'SHEET'], ['WK200/M125/K275', 'C/F', 7960, 'SHEET'], ['WK200/M125/WK150', 'C/F', 7017, 'SHEET'],
            ['WK200/M125/WK200', 'C/F', 7633, 'SHEET'], ['K150/M150/K150', 'C/F', 5377, 'SHEET'], ['K200/M150/K200', 'C/F', 6489, 'SHEET'],
            ['WK150/M150/K150', 'C/F', 5916, 'SHEET'], ['K275/M150/K275', 'C/F', 9155, 'SHEET'],
            ['M125/M125/M125', 'BC/F', 7252, 'SHEET'], ['K125/M125/M125', 'BC/F', 7379, 'SHEET'], ['K125/M125/K125', 'BC/F', 7507, 'SHEET'],
            ['K150/M125/M125', 'BC/F', 7600, 'SHEET'], ['K150/M125/K125', 'BC/F', 7728, 'SHEET'], ['K150/M125/K150', 'BC/F', 7948, 'SHEET'],
            ['K200/M125/M125', 'BC/F', 8351, 'SHEET'], ['K200/M125/K125', 'BC/F', 8483, 'SHEET'], ['K200/M125/K150', 'BC/F', 8713, 'SHEET'],
            ['K200/M125/K200', 'BC/F', 9171, 'SHEET'], ['K275/M125/M125', 'BC/F', 9726, 'SHEET'], ['K275/M125/K125', 'BC/F', 9863, 'SHEET'],
            ['K275/M125/K150', 'BC/F', 10101, 'SHEET'], ['K275/M125/K200', 'BC/F', 10577, 'SHEET'], ['K275/M125/K275', 'BC/F', 12058, 'SHEET'],
            ['WK150/M125/M125', 'BC/F', 8236, 'SHEET'], ['WK150/M125/K125', 'BC/F', 8369, 'SHEET'], ['WK150/M125/K150', 'BC/F', 8598, 'SHEET'],
            ['WK150/M125/K200', 'BC/F', 9057, 'SHEET'], ['WK150/M125/K275', 'BC/F', 10458, 'SHEET'], ['WK150/M125/WK150', 'BC/F', 9605, 'SHEET'],
            ['WK200/M125/M125', 'BC/F', 8810, 'SHEET'], ['WK200/M125/K125', 'BC/F', 8942, 'SHEET'], ['WK200/M125/K150', 'BC/F', 9171, 'SHEET'],
            ['WK200/M125/K200', 'BC/F', 9630, 'SHEET'], ['WK200/M125/K275', 'BC/F', 11053, 'SHEET'], ['WK200/M125/WK150', 'BC/F', 10220, 'SHEET'],
            ['WK200/M125/WK200', 'BC/F', 10836, 'SHEET'], ['K150/M150/K150', 'BC/F', 8548, 'SHEET'], ['K200/M150/K200', 'BC/F', 9771, 'SHEET'],
            ['WK150/M150/K150', 'BC/F', 9198, 'SHEET'], ['K275/M150/K275', 'BC/F', 12658, 'SHEET'],
            ['SF M 125 / M 125', 'E/F', 2500, 'SF'], ['SF M 125 / K 125', 'E/F', 2600, 'SF'], ['SF M 125 / K 150', 'E/F', 0, 'SF'],
            ['SF M 125 / WK 150', 'E/F', 0, 'SF'], ['SF BF M 125 / M 125', 'E/F', 0, 'SF'],
        ];
    }
};
