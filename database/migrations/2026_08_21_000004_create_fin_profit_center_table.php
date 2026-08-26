<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// itemmaster_delta.md §4 -- dimensi profit center utk item master & jurnal.
// Draf dokumen mengasumsikan pola code/name polos; DICEK ke fin_cost_center
// AKTUAL (2026-08-21): PK-nya cc_id (bukan generic id), kolom cc_code/cc_name,
// plus cc_group (enum) & org_dept_id yang tak disebut draf. fin_profit_center
// disesuaikan supaya "sepasang" secara PK/kolom inti (pc_id/pc_code/pc_name),
// TANPA cc_group/org_dept_id -- profit center adalah dimensi pelaporan lintas
// departemen, tidak punya relasi 1:1 ke satu departemen seperti cost center.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2acc');

        if (! $db->hasTable('fin_profit_center')) {
            $db->create('fin_profit_center', function (Blueprint $table) {
                $table->id('pc_id');
                $table->string('pc_code', 30)->unique()->comment("mis. 'PC-PACKAGING', 'PC-COMMERCIAL'");
                $table->string('pc_name', 100);
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 50)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 50)->nullable();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            });

            $now = now();
            DB::connection('tm2acc')->table('fin_profit_center')->insert([
                ['pc_code' => 'PC-PACKAGING', 'pc_name' => 'Packaging', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['pc_code' => 'PC-COMMERCIAL', 'pc_name' => 'Commercial Print', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('tm2acc')->dropIfExists('fin_profit_center');
    }
};
