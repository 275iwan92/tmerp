<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Jalur BOM & BOP ProDev -> Work Order (BACKLOG §19.3, instruksi user
// 2026-09-18). Operasi WO menerima toleransi & target dari BOP; bahan WO
// menerima satuan & peran baris dari BOM (qty_required sudah ada).
// Kolom waste_qty/spoilage_qty yang sudah ada dipakai apa adanya.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2prd')->table('prd_wo_operation', function (Blueprint $t) {
            $t->decimal('tolerance_qty', 14, 2)->nullable()->after('waste_uom')->comment('toleransi kelebihan dari BOP');
            $t->string('tolerance_uom', 10)->nullable()->after('tolerance_qty');
        });

        Schema::connection('tm2prd')->table('prd_wo_material', function (Blueprint $t) {
            $t->string('uom', 10)->nullable()->after('qty_standar');
            $t->string('material_role', 30)->nullable()->after('uom')->comment('peran baris BOM, mis. KERTAS PLANO');
            $t->string('material_name', 200)->nullable()->after('material_role')->comment('snapshot nama bahan');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->table('prd_wo_material', fn (Blueprint $t) => $t->dropColumn(['uom', 'material_role', 'material_name']));
        Schema::connection('tm2prd')->table('prd_wo_operation', fn (Blueprint $t) => $t->dropColumn(['tolerance_qty', 'tolerance_uom']));
    }
};
