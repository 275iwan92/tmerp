<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Takaran pemakaian & persen waste yang belajar dari data nyata (instruksi
// user 2026-09-18: "setelah satu periode bulan ... bisakah dibentuk auto
// update ... dengan catatan officer tetap bisa menentukan secara input
// manual").
//
// Pola: engine MENGUSULKAN (rate_suggested / waste_suggested + jumlah sampel),
// officer/admin yang memutuskan -- kecuali aturan itu di-set auto_apply.
// Nilai manual yang diisi officer TIDAK pernah ditimpa selama auto_apply = 0.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_bop_rule', function (Blueprint $t) {
            $t->decimal('rate_suggested', 12, 4)->nullable()->after('usage_rate')->comment('usulan engine dari pemakaian nyata');
            $t->decimal('waste_suggested', 5, 2)->nullable()->after('rate_suggested');
            $t->unsignedInteger('suggest_sample')->nullable()->after('waste_suggested')->comment('jumlah WO yang dipakai menghitung usulan');
            $t->timestamp('suggest_at')->nullable()->after('suggest_sample');
            $t->boolean('auto_apply')->default(false)->after('suggest_at')->comment('1 = usulan langsung dipakai tanpa persetujuan');
            $t->string('rate_source', 10)->default('MANUAL')->after('auto_apply')->comment('MANUAL | AUTO');
        });

        DB::connection('tm2sys')->table('sys_constant')->updateOrInsert(['constant_key' => 'PRODEV_RATE_MIN_SAMPLE'], [
            'category_code' => 'PRODUCTION', 'constant_group' => 'PRODEV', 'constant_number' => 5,
            'description' => 'Minimal jumlah WO selesai sebelum usulan takaran/waste otomatis dianggap layak',
            'activate' => 1, 'created_at' => now(), 'updated_at' => now(),
            'created_by' => 'claude_code', 'updated_by' => 'claude_code',
        ]);
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_bop_rule', fn (Blueprint $t) => $t->dropColumn([
            'rate_suggested', 'waste_suggested', 'suggest_sample', 'suggest_at', 'auto_apply', 'rate_source',
        ]));

        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'PRODEV_RATE_MIN_SAMPLE')->delete();
    }
};
