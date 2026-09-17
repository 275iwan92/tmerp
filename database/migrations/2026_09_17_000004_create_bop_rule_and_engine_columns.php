<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fondasi engine BOM / Ink / BOP (BACKLOG §18, keputusan user 2026-09-17).
//
// Syarat user: TIDAK ada tabel dobel. Karena itu:
// - Routing standar offset DAN sisipan per finishing disimpan di SATU tabel
//   aturan (prod_bop_rule): rule_scope STANDARD = langkah baku, FINISHING =
//   langkah yang muncul hanya bila part memakai finishing tsb. Admin bisa
//   ubah urutan (step_seq) & penggabungan (merge_group) tanpa ubah kode.
// - Finishing, tinta, proses, material tetap memakai tabel yang sudah ada
//   (ref_finishing_type, prod_process, prod_part_finishing, prod_part_ink,
//   prod_part_process, prod_part_material). Yang baru di tm2sdi hanya
//   pasangan snapshot Inquiry (iqp_part_finishing, iqp_part_ink) -- pola yang
//   sama dgn iqp_part_material/iqp_part_process (aturan simpan 2 arah).
//
// Urutan default diambil dari analisa 1.072 kartu legacy
// (prod_card_process_legacy, rata-rata posisi relatif tiap proses):
// CETAK .05 · VARNISH .17 · QC CETAK .26 · LAMINATING .29 · UV .31 ·
// HOTPRINT .38 · LAMINASI .39 · PLONG .50 · EMBOSE .52 · KUPAS .62 ·
// LEM .74 · HITUNG .84 · PACK/WRAPPING .98-1.0.
// Emboss di legacy 11x terpisah, 3x "PLONG + EMBOSE" -> digabung hanya bila
// admin set merge_group (default digabung, sesuai Master Card AL SAMSI).
return new class extends Migration
{
    private const NEW_PROCESSES = ['VARNISH', 'HOT PRINT', 'FOIL STAMPING', 'EMBOSS', 'DEBOSS'];

    // [scope, finishing_name|null, seq, process_name, label, merge_group, merge_label,
    //  machine_role, condition_code, material_pattern, material_rule]
    private const RULES = [
        ['STANDARD', null, 10, 'POTONG', 'Potong Plano', null, null, null, 'PLANO_NEEDS_CUT', null, null],
        ['STANDARD', null, 20, 'CETAK', 'Cetak di mesin: {machine}', null, null, 'PRINT', null, null, null],
        ['STANDARD', null, 25, 'QC CETAK', 'QC Inspection', null, null, null, null, null, null],
        ['FINISHING', 'Varnish', 30, 'VARNISH', 'Varnish {attr}', null, null, null, null, null, null],
        ['FINISHING', 'Laminating Doff', 40, 'LAMINATING DOFT', 'Laminating Doff (Plastik {roll} cm)', null, null, null, null,
            'PLASTIKLAMDOFF%', 'ROLL_WIDTH_GE_SHEET'],
        ['FINISHING', 'Laminating Glossy', 40, 'LAMINATING GLOSSY', 'Laminating Glossy (Plastik {roll} cm)', null, null, null, null,
            'PLASTIKLAMINATINGGLOSY%|PLASTIKLAMGLOSY%', 'ROLL_WIDTH_GE_SHEET'],
        ['FINISHING', 'UV Spot', 50, 'UV', 'UV Spot', null, null, null, null, null, null],
        ['FINISHING', 'Hot Print', 60, 'HOT PRINT', 'Hot Print {attr}', null, null, null, null, 'FOIL HOT PRINT%', 'MATCH_ATTRIBUTE'],
        ['FINISHING', 'Foil Stamping', 60, 'FOIL STAMPING', 'Foil Stamping {attr}', null, null, null, null, 'FOIL%', 'MATCH_ATTRIBUTE'],
        ['STANDARD', null, 65, 'LAMINASI', 'Laminasi Bahan', null, null, null, 'MULTI_LAYER', null, null],
        ['STANDARD', null, 70, 'PLONG', 'Plong Depan', 'PLONG', null, 'DIECUT', null, null, null],
        ['FINISHING', 'Emboss', 72, 'EMBOSS', 'Emboss', 'PLONG', '+ Emboss', null, null, null, null],
        ['FINISHING', 'Deboss', 72, 'DEBOSS', 'Deboss', 'PLONG', '+ Deboss', null, null, null, null],
        ['STANDARD', null, 80, 'KUPAS', 'Kupas', 'KUPAS', null, null, null, null, null],
        ['STANDARD', null, 82, 'LEM MESIN', 'Lem', null, null, null, 'NEEDS_GLUE', null, null],
        ['STANDARD', null, 84, 'HITUNG', 'Hitung', 'KUPAS', '& Hitung', null, null, null, null],
        ['STANDARD', null, 90, 'WRAPPING', 'Pack Wrapping /{pack}pcs', null, null, null, null, null, null],
    ];

    public function up(): void
    {
        $mst = Schema::connection('tm2mst');
        $sdi = Schema::connection('tm2sdi');

        $mst->create('prod_bop_rule', function (Blueprint $t) {
            $t->bigIncrements('id_bop_rule');
            $t->string('rule_scope', 10)->comment('STANDARD | FINISHING');
            $t->unsignedBigInteger('finishing_type_id')->nullable()->comment('wajib bila FINISHING');
            $t->string('part_type', 30)->nullable()->comment('NULL = semua tipe part');
            $t->integer('step_seq')->comment('posisi urutan; admin boleh ubah');
            $t->unsignedBigInteger('process_id');
            $t->string('step_label', 120)->comment('token: {machine} {attr} {roll} {pack}');
            $t->string('merge_group', 20)->nullable()->comment('langkah bersebelahan dgn grup sama digabung jadi 1 baris');
            $t->string('merge_label', 60)->nullable()->comment('teks sambungan saat digabung, mis. "+ Emboss"');
            $t->string('machine_role', 10)->nullable()->comment('PRINT = mesin cetak ProDev, DIECUT = mesin pond ProDev');
            $t->string('condition_code', 30)->nullable()->comment('PLANO_NEEDS_CUT | MULTI_LAYER | NEEDS_GLUE');
            $t->string('material_pattern', 150)->nullable()->comment('pola LIKE mat_raw, dipisah |');
            $t->string('material_rule', 30)->nullable()->comment('ROLL_WIDTH_GE_SHEET | MATCH_ATTRIBUTE');
            $t->boolean('activate')->default(true);
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();

            $t->foreign('finishing_type_id')->references('id_finishing_type')->on('ref_finishing_type');
            $t->foreign('process_id')->references('id_process')->on('prod_process');
            $t->index(['rule_scope', 'activate', 'step_seq']);
        });

        $mst->table('prod_part_finishing', function (Blueprint $t) {
            $t->string('attribute', 60)->nullable()->after('finishing_type_id')->comment('varian, mis. warna foil "Gold Puyeh"');
        });

        $mst->table('prod_part_ink', function (Blueprint $t) {
            $t->decimal('coverage_pct', 6, 2)->nullable()->after('ink_name');
            $t->decimal('coverage_cm2', 12, 2)->nullable()->after('coverage_pct')->comment('per pcs (area blank)');
            $t->char('color_hex', 7)->nullable()->after('coverage_cm2');
            $t->string('source', 10)->default('MANUAL')->after('plate_ref')->comment('ENGINE | MANUAL');
        });

        $mst->table('prod_part_process', function (Blueprint $t) {
            $t->string('source', 10)->default('MANUAL')->after('note')->comment('ENGINE | MANUAL');
        });

        $mst->table('prod_part_material', function (Blueprint $t) {
            $t->decimal('qty_per_sheet', 14, 4)->nullable()->after('size_l');
            $t->string('uom', 10)->nullable()->after('qty_per_sheet');
            $t->string('source', 10)->default('MANUAL')->after('note')->comment('ENGINE | MANUAL');
        });

        $mst->table('prod_layout_group', function (Blueprint $t) {
            $t->string('knife_code', 80)->nullable()->after('up_count');
            $t->decimal('knife_w_cm', 8, 2)->nullable()->after('knife_code');
            $t->decimal('knife_h_cm', 8, 2)->nullable()->after('knife_w_cm');
        });

        $sdi->create('iqp_part_finishing', function (Blueprint $t) {
            $t->bigIncrements('id_part_finishing');
            $t->unsignedBigInteger('part_id');
            $t->unsignedSmallInteger('seq');
            $t->unsignedBigInteger('finishing_type_id')->comment('tm2mst.ref_finishing_type (lintas DB, tanpa FK)');
            $t->string('finishing_name', 100)->comment('snapshot nama');
            $t->string('attribute', 60)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();

            $t->foreign('part_id')->references('id_part')->on('iqp_part')->cascadeOnDelete();
        });

        $sdi->create('iqp_part_ink', function (Blueprint $t) {
            $t->bigIncrements('id_part_ink');
            $t->unsignedBigInteger('part_id');
            $t->unsignedTinyInteger('pass_seq');
            $t->string('pass_label', 5)->nullable();
            $t->unsignedTinyInteger('unit_no');
            $t->string('ink_name', 40);
            $t->decimal('coverage_pct', 6, 2)->nullable();
            $t->decimal('coverage_cm2', 12, 2)->nullable();
            $t->char('color_hex', 7)->nullable();
            $t->string('source', 10)->default('MANUAL');
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();

            $t->foreign('part_id')->references('id_part')->on('iqp_part')->cascadeOnDelete();
        });

        $sdi->table('iqp_part', function (Blueprint $t) {
            $t->unsignedInteger('pack_qty')->nullable()->after('qty_per_sku')->comment('isi per pack');
            $t->json('artwork_colors')->nullable()->after('artwork_original_filename')->comment('hasil analisa warna artwork');
        });

        $sdi->table('iqp_part_process', function (Blueprint $t) {
            $t->string('note', 255)->nullable()->after('qty_factor');
            $t->string('source', 10)->default('MANUAL')->after('note');
        });

        $sdi->table('iqp_part_material', function (Blueprint $t) {
            $t->decimal('qty_per_sheet', 14, 4)->nullable()->after('size_l');
            $t->string('uom', 10)->nullable()->after('qty_per_sheet');
            $t->string('source', 10)->default('MANUAL')->after('material_source');
        });

        $sdi->table('iqp_layout', function (Blueprint $t) {
            $t->string('knife_code', 80)->nullable()->after('up_count');
            $t->decimal('knife_w_cm', 8, 2)->nullable()->after('knife_code');
            $t->decimal('knife_h_cm', 8, 2)->nullable()->after('knife_w_cm');
        });

        $this->seedRules();
    }

    private function seedRules(): void
    {
        $db = DB::connection('tm2mst');
        $now = now();

        foreach (self::NEW_PROCESSES as $name) {
            if (! $db->table('prod_process')->where('process_name', $name)->exists()) {
                $db->table('prod_process')->insert([
                    'process_name' => $name, 'activate' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                    'created_by' => 'claude_code', 'updated_by' => 'claude_code',
                ]);
            }
        }

        $process = $db->table('prod_process')->pluck('id_process', 'process_name');
        $finishing = $db->table('ref_finishing_type')->pluck('id_finishing_type', 'finishing_name');

        foreach (self::RULES as [$scope, $fin, $seq, $proc, $label, $merge, $mergeLabel, $role, $cond, $pattern, $matRule]) {
            $db->table('prod_bop_rule')->insert([
                'rule_scope' => $scope,
                'finishing_type_id' => $fin ? $finishing[$fin] : null,
                'step_seq' => $seq,
                'process_id' => $process[$proc],
                'step_label' => $label,
                'merge_group' => $merge,
                'merge_label' => $mergeLabel,
                'machine_role' => $role,
                'condition_code' => $cond,
                'material_pattern' => $pattern,
                'material_rule' => $matRule,
                'activate' => 1,
                'created_at' => $now, 'updated_at' => $now,
                'created_by' => 'claude_code', 'updated_by' => 'claude_code',
            ]);
        }
    }

    public function down(): void
    {
        $mst = Schema::connection('tm2mst');
        $sdi = Schema::connection('tm2sdi');

        $sdi->table('iqp_layout', fn (Blueprint $t) => $t->dropColumn(['knife_code', 'knife_w_cm', 'knife_h_cm']));
        $sdi->table('iqp_part_material', fn (Blueprint $t) => $t->dropColumn(['qty_per_sheet', 'uom', 'source']));
        $sdi->table('iqp_part_process', fn (Blueprint $t) => $t->dropColumn(['note', 'source']));
        $sdi->table('iqp_part', fn (Blueprint $t) => $t->dropColumn(['pack_qty', 'artwork_colors']));
        $sdi->dropIfExists('iqp_part_ink');
        $sdi->dropIfExists('iqp_part_finishing');

        $mst->table('prod_layout_group', fn (Blueprint $t) => $t->dropColumn(['knife_code', 'knife_w_cm', 'knife_h_cm']));
        $mst->table('prod_part_material', fn (Blueprint $t) => $t->dropColumn(['qty_per_sheet', 'uom', 'source']));
        $mst->table('prod_part_process', fn (Blueprint $t) => $t->dropColumn('source'));
        $mst->table('prod_part_ink', fn (Blueprint $t) => $t->dropColumn(['coverage_pct', 'coverage_cm2', 'color_hex', 'source']));
        $mst->table('prod_part_finishing', fn (Blueprint $t) => $t->dropColumn('attribute'));
        $mst->dropIfExists('prod_bop_rule');

        // Proses baru dibiarkan: bisa keburu dipakai routing part.
    }
};
