<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kolom placeholder saja (permintaan user 2026-09-05) -- rumusan spoilage/waste
// menyusul. Dipisah qty+uom krn tiap proses beda satuan (mis. CETAK = lembar,
// LAMINASI = meter, LEM = kg) -- tidak dipaksa 1 UOM seragam sepanjang Bill of
// Process, sama pola dgn Bill of Material (delta doc §5 poin 4). uom disimpan
// sbg uom_code teks -- LOGICAL FK tm2mst.uom_unit (lintas-DB, CLAUDE.md §2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2prd')->table('prd_wo_operation', function (Blueprint $table) {
            $table->decimal('spoilage_qty', 18, 4)->nullable()->after('qty')->comment('Estimasi spoilage (makeready) -- formula menyusul');
            $table->string('spoilage_uom', 20)->nullable()->after('spoilage_qty')->comment('LOGICAL FK tm2mst.uom_unit.uom_code -- beda per proses');
            $table->decimal('waste_qty', 18, 4)->nullable()->after('spoilage_uom')->comment('Estimasi waste (running) -- formula menyusul');
            $table->string('waste_uom', 20)->nullable()->after('waste_qty')->comment('LOGICAL FK tm2mst.uom_unit.uom_code -- beda per proses');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->table('prd_wo_operation', function (Blueprint $table) {
            $table->dropColumn(['spoilage_qty', 'spoilage_uom', 'waste_qty', 'waste_uom']);
        });
    }
};
