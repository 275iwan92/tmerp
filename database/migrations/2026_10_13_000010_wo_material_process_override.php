<?php

/*
 * REMARK
 * Tujuan       : Menambah prd_wo_material.process_id dan override_reason.
 * Sumber hulu  : prd_wo_material (tm2prd).
 * Input        : -
 * Output       : Dua kolom baru.
 * Dipakai oleh : Ppic::bomSheet, WoGenerationService, bomCells.
 * Status       : Sudah dijalankan di ddt* saja; jalankan di tm2* saat rilis.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// process_id = the BOP process that uses the material line; override_reason = why the officer changed qty_required
// (qty_standar keeps the engine number, so "edited" = qty_required <> qty_standar).
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('tm2prd');
        $s->table('prd_wo_material', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('prd_wo_material', 'process_id')) {
                $t->unsignedBigInteger('process_id')->nullable()->after('material_role');
            }
            if (! $s->hasColumn('prd_wo_material', 'override_reason')) {
                $t->string('override_reason', 190)->nullable()->after('qty_standar');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->table('prd_wo_material', fn (Blueprint $t) => $t->dropColumn(['process_id', 'override_reason']));
    }
};
