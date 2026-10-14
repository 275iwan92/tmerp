<?php

/*
 * REMARK
 * Tujuan       : Kolom Waste dan Spill per baris Bill of Material WO: nilai ProDev + timpaan officer.
 * Sumber hulu  : ProDev (BOP Work Order: prd_wo_operation.waste_qty dan tolerance_qty = SPILL) lewat WoGenerationService::regenerateBom.
 * Input        : -
 * Output       : prd_wo_material.waste_engine_qty / spill_engine_qty (nilai ProDev, isi sistem), waste_override_qty / spill_override_qty (timpaan officer, NULL = pakai nilai ProDev).
 * Dipakai oleh : Ppic::bomSheet, PpicController::bomCells, WoGenerationService::regenerateBom, SheetReset (RESET membuang timpaan), Ppic::fingerprint.
 * Status       : Dijalankan di ddt* saja; menunggu Jo untuk rilis ke tm2*. Dapat dibalik (down).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = ['waste_engine_qty', 'spill_engine_qty', 'waste_override_qty', 'spill_override_qty'];

    public function up(): void
    {
        $s = Schema::connection('tm2prd');
        $s->table('prd_wo_material', function (Blueprint $t) use ($s) {
            foreach (self::COLUMNS as $c) {
                if (! $s->hasColumn('prd_wo_material', $c)) {
                    $t->decimal($c, 18, 4)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->table('prd_wo_material', fn (Blueprint $t) => $t->dropColumn(self::COLUMNS));
    }
};
