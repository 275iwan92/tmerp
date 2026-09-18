<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Keputusan user 2026-09-18 (setelah cocok Excel HPP): toleransi per part, margin
// NON PPN & PPN terpisah, diskon RCA bebas 20-26%. Konstanta/supplier/tier diskon
// diselaraskan ke dump legacy docs/ddl/tmhpp.sql (m_hpp_constants, m_sheet_supplier,
// m_sheet_discount_tier). Detail: procost_formula_library.md §IX.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $t) {
            $t->decimal('tolerance_pct', 6, 2)->nullable()->after('qty_per_set')
                ->comment('Toleransi per part (%), tergantung produk & tingkat kesulitan. NULL = ikut tolerance_pct tier');
        });
        Schema::connection('tm2sdi')->table('iqp_costing_price_tier', function (Blueprint $t) {
            $t->decimal('margin_ppn_pct', 6, 2)->nullable()->after('margin_pct')
                ->comment('Margin jalur INCLUDE PPN (Excel J47/K47). margin_pct = margin jalur NON PPN (Excel G45). NULL = sama dgn margin_pct');
        });

        Schema::connection('tm2mst')->table('mat_sheet_discount_tier', function (Blueprint $t) {
            $t->string('part_type_code', 20)->nullable()->after('supplier_code')->comment('NULL = semua tipe part');
            $t->unsignedBigInteger('sheet_rate_id')->nullable()->after('part_type_code')->comment('mat_sheet_rate; NULL = semua substansi');
            $t->date('effective_date')->default('2000-01-01')->after('discount_pct');
            $t->string('notes', 150)->nullable()->after('effective_date');
        });

        $mst = DB::connection('tm2mst');
        $now = now();
        // Tier lama V2 (UJK -5% + batas KURANG <300RM dari Excel COCO, RCA rm_to eksklusif) diganti persis m_sheet_discount_tier legacy.
        $mst->table('mat_sheet_discount_tier')->update(['activate' => false, 'notes' => 'diganti tier legacy tmhpp 2026-09-18', 'updated_at' => $now]);
        $rate = fn (string $name) => $mst->table('mat_sheet_rate')->where('substance_name', $name)->where('flute_type', 'B/F')->value('id_sheet_rate');
        $k125 = $rate('K125/M125/M125');   // legacy doc_id 2
        $wk150 = $rate('WK150/M125/M125'); // legacy doc_id 16
        foreach ([
            ['UJK', $k125, 0, null, -0.1000, '2026-01-01', false, 'K125 flat -10% (Mar2026, deprecated)'],
            ['UJK', $k125, 0, null, -0.0500, '2026-04-01', true, 'K125 flat -5% (Apr2026, aktif)'],
            ['UJK', $wk150, 0, null, -0.1500, '2026-01-01', false, 'WK150 flat -15% (Apr2026, deprecated)'],
            ['UJK', $wk150, 0, null, -0.0650, '2026-11-01', true, 'WK150 flat -6.5% (Nov2026, aktif)'],
            ['UJK', null, 0, null, -0.1000, '2026-01-01', true, 'UJK default fallback flat -10%'],
            ['RCA', $k125, 0, 299.99, 0.2000, '2026-01-01', true, 'K125, <300RM disc 20%'],
            ['RCA', $k125, 300, 499.99, 0.2000, '2026-01-01', true, 'K125, 300-499RM disc 20%'],
            ['RCA', $k125, 500, 999.99, 0.2500, '2026-01-01', true, 'K125, 500-999RM disc 25%'],
            ['RCA', $k125, 1000, 9999999, 0.2600, '2026-01-01', true, 'K125, >=1000RM disc 26%'],
            ['RCA', $wk150, 0, null, 0.2250, '2026-01-01', true, 'WK150, flat disc 22.5%'],
            ['RCA', null, 0, 299.99, 0.2000, '2026-01-01', true, 'RCA default, <300RM 20%'],
            ['RCA', null, 300, 499.99, 0.2000, '2026-01-01', true, 'RCA default, 300-499RM 20%'],
            ['RCA', null, 500, 999.99, 0.2500, '2026-01-01', true, 'RCA default, 500-999RM 25%'],
            ['RCA', null, 1000, 9999999, 0.2600, '2026-01-01', true, 'RCA default, >=1000RM 26%'],
        ] as [$sup, $sr, $from, $to, $disc, $eff, $active, $note]) {
            $mst->table('mat_sheet_discount_tier')->insert([
                'supplier_code' => $sup, 'sheet_rate_id' => $sr, 'rm_from' => $from, 'rm_to' => $to, 'discount_pct' => $disc,
                'effective_date' => $eff, 'notes' => $note, 'activate' => $active, 'created_by' => 'legacy_tmhpp', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $sys = DB::connection('tm2sys');
        $sys->table('sys_constant')->where('constant_key', 'SHEET_MACHINE_WIDTH_CM_RCA')->update(['constant_number' => 215, 'description' => 'Lebar mesin corrugator RCA (legacy m_sheet_supplier.machine_width_cm). UJK = SHEET_MACHINE_WIDTH_CM (180, keputusan user; legacy 170)', 'updated_at' => $now]);
        foreach ([
            ['SHEET_TRIM_GAP_CM_RCA', 5, 'Gap trim RCA per run (legacy m_sheet_supplier.trim_allowance_cm). Supplier lain = SHEET_TRIM_GAP_CM'],
            ['SHEET_DISCOUNT_MIN_RCA', 0.20, 'Diskon sheet RCA minimum (keputusan user: bebas 20-26%)'],
            ['SHEET_DISCOUNT_MAX_RCA', 0.26, 'Diskon sheet RCA maksimum (keputusan user: bebas 20-26%)'],
            ['KNIFE_RATE_DC', 900000, 'Rate pisau per order DC/DC+SF/DC+SHEET (legacy m_hpp_constants). Fallback bila prod_part_type.pisau_rate_rp kosong'],
            ['KNIFE_RATE_KRAFT', 1500000, 'Rate pisau per order KRAFT (legacy m_hpp_constants)'],
            ['KNIFE_RATE_OB', 0, 'OB/SHEET tanpa pisau die-cut (legacy m_hpp_constants)'],
            ['PCS_PER_LB_DEFAULT', 1, 'Default isi per LB bila kosong (legacy m_hpp_constants)'],
            ['CUT_PER_PLANO_DEFAULT', 1, 'Default potong per plano bila kosong (legacy m_hpp_constants)'],
        ] as [$key, $num, $desc]) {
            $sys->table('sys_constant')->updateOrInsert(['constant_key' => $key], [
                'category_code' => 'PRODUCTION', 'constant_group' => 'COSTING', 'constant_number' => $num, 'constant_text' => null,
                'description' => $desc, 'activate' => true, 'created_by' => 'legacy_tmhpp', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['SHEET_TRIM_GAP_CM_RCA', 'SHEET_DISCOUNT_MIN_RCA', 'SHEET_DISCOUNT_MAX_RCA', 'KNIFE_RATE_DC', 'KNIFE_RATE_KRAFT', 'KNIFE_RATE_OB', 'PCS_PER_LB_DEFAULT', 'CUT_PER_PLANO_DEFAULT'])->delete();
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'SHEET_MACHINE_WIDTH_CM_RCA')->update(['constant_number' => 220]);
        $mst = DB::connection('tm2mst');
        $mst->table('mat_sheet_discount_tier')->whereNotNull('effective_date')->where('effective_date', '<>', '2000-01-01')->delete();
        $mst->table('mat_sheet_discount_tier')->update(['activate' => true]);
        Schema::connection('tm2mst')->table('mat_sheet_discount_tier', fn (Blueprint $t) => $t->dropColumn(['part_type_code', 'sheet_rate_id', 'effective_date', 'notes']));
        Schema::connection('tm2sdi')->table('iqp_costing_price_tier', fn (Blueprint $t) => $t->dropColumn('margin_ppn_pct'));
        Schema::connection('tm2sdi')->table('iqp_costing_part', fn (Blueprint $t) => $t->dropColumn('tolerance_pct'));
    }
};
