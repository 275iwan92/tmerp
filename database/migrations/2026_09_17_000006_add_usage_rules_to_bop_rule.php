<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BOM lengkap dengan estimasi pemakaian (user 2026-09-17: "bahan utama kertas
// dan sheet ... tinta dan lem misal untuk laminasi dengan estimasi pemakaian
// ... di gudang tersedia atau tidak").
//
// Aturan bahan tetap di prod_bop_rule (tidak bikin tabel baru): baris boleh
// "bahan saja" (process_id NULL) -- mis. lem laminasi ikut finishing
// Laminating tanpa menambah langkah routing. Takaran pemakaian disimpan per
// aturan supaya admin bisa mengubahnya di Setting Aturan BOP:
//   usage_basis   SHEET_LENGTH  meter per lembar (sisi pendek lembar)
//                 SHEET_AREA    gram per m2 lembar cetak
//                 INK_COVERAGE  gram per m2 area tertutup tinta
//                 PER_PCS       gram per pcs produk jadi
//                 AREA_UNKNOWN  luas pakai belum bisa dihitung (mis. foil)
//
// Takaran awal di bawah = ESTIMASI UMUM industri, BUKAN data pabrik ini
// (ditandai di kolom note) -- wajib dikoreksi admin dari data pemakaian nyata.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_bop_rule', function (Blueprint $t) {
            $t->unsignedBigInteger('process_id')->nullable()->change();
            $t->string('step_label', 120)->nullable()->change();
            $t->string('material_role', 30)->nullable()->after('material_rule')->comment('nama baris BOM, mis. LEM LAMINASI');
            $t->string('usage_basis', 20)->nullable()->after('material_role');
            $t->decimal('usage_rate', 12, 4)->nullable()->after('usage_basis')->comment('g/m2, g/pcs, atau m/m');
        });

        $db = DB::connection('tm2mst');
        $now = now();
        $fin = $db->table('ref_finishing_type')->pluck('id_finishing_type', 'finishing_name');
        $audit = ['activate' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'claude_code', 'updated_by' => 'claude_code'];
        $estimasi = 'Takaran estimasi awal -- koreksi dari data pemakaian nyata';

        foreach (['Laminating Doff', 'Laminating Glossy'] as $name) {
            $db->table('prod_bop_rule')->where('finishing_type_id', $fin[$name])->whereNotNull('process_id')->update([
                'material_role' => 'PLASTIK LAMINASI', 'usage_basis' => 'SHEET_LENGTH', 'usage_rate' => 1,
            ]);
        }

        $db->table('prod_bop_rule')->where('finishing_type_id', $fin['Hot Print'])->update([
            'material_role' => 'FOIL HOT PRINT', 'usage_basis' => 'AREA_UNKNOWN',
        ]);
        $db->table('prod_bop_rule')->where('finishing_type_id', $fin['Foil Stamping'])->update([
            'material_role' => 'FOIL', 'usage_basis' => 'AREA_UNKNOWN',
        ]);

        $rows = [
            // [scope, finishing, condition, seq, role, pattern, rule, basis, rate]
            ['FINISHING', 'Laminating Doff', null, 41, 'LEM LAMINASI', '%LEM LAMINASI%|%LEM LAMINATING%', 'BEST_STOCK', 'SHEET_AREA', 10],
            ['FINISHING', 'Laminating Glossy', null, 41, 'LEM LAMINASI', '%LEM LAMINASI%|%LEM LAMINATING%', 'BEST_STOCK', 'SHEET_AREA', 10],
            ['FINISHING', 'UV Spot', null, 51, 'VARNISH UV', '%VARNISH UV%|%UV VARNISH%|%COATING UV%', 'BEST_STOCK', 'AREA_UNKNOWN', null],
            ['FINISHING', 'Varnish', null, 31, 'VARNISH', '%VARNISH%', 'BEST_STOCK', 'AREA_UNKNOWN', null],
            ['STANDARD', null, null, 21, 'TINTA', 'TINTA SW 490 N PR. {ink}%|BRITE INK {ink}%|TINTA RAPIDA {ink}%', 'INK_BY_NAME', 'INK_COVERAGE', 1.5],
            ['STANDARD', null, 'MULTI_LAYER', 66, 'LEM LAMINASI BAHAN', '%LEM LAMINASI%', 'BEST_STOCK', 'SHEET_AREA', 15],
            ['STANDARD', null, 'NEEDS_GLUE', 83, 'LEM BOX', '%LEM KUNING%|%LEM PUTIH%', 'BEST_STOCK', 'PER_PCS', 0.15],
        ];

        foreach ($rows as [$scope, $finName, $cond, $seq, $role, $pattern, $rule, $basis, $rate]) {
            $db->table('prod_bop_rule')->insert([
                'rule_scope' => $scope,
                'finishing_type_id' => $finName ? $fin[$finName] : null,
                'step_seq' => $seq,
                'process_id' => null,
                'step_label' => null,
                'condition_code' => $cond,
                'material_pattern' => $pattern,
                'material_rule' => $rule,
                'material_role' => $role,
                'usage_basis' => $basis,
                'usage_rate' => $rate,
                'note' => $rate !== null ? $estimasi : 'Luas pakai belum bisa dihitung otomatis',
            ] + $audit);
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');
        $db->table('prod_bop_rule')->whereNull('process_id')->delete();

        Schema::connection('tm2mst')->table('prod_bop_rule', function (Blueprint $t) {
            $t->dropColumn(['material_role', 'usage_basis', 'usage_rate']);
        });
    }
};
