<?php

/*
 * REMARK
 * Tujuan       : Dua kolom di prd_wo: earliest_start (tanggal awal mulai WO, input officer untuk engine) dan pending_header (urutan BOP / Priority yang menunggu PROCESS).
 * Sumber hulu  : Form WO (PpicController::earliestStart, reorderOps, prioritize) -> PlanChangeService.
 * Input        : -
 * Output       : prd_wo.earliest_start DATETIME NULL; prd_wo.pending_header JSON NULL {order:{ids,reason}, priority:{kind,reason}}.
 * Dipakai oleh : PlanLoader (lantai waktu mulai), Ppic::fingerprint / processState / bopSheet, PlanChangeService::applyPendingHeader (di PROCESS).
 * Status       : Dijalankan di ddt* saja; menunggu persetujuan Jo untuk rilis ke tm2*.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('tm2prd');
        $s->table('prd_wo', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('prd_wo', 'earliest_start')) {
                $t->dateTime('earliest_start')->nullable();
            }
            if (! $s->hasColumn('prd_wo', 'pending_header')) {
                $t->json('pending_header')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->table('prd_wo', fn (Blueprint $t) => $t->dropColumn(['earliest_start', 'pending_header']));
    }
};
