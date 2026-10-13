<?php

/*
 * REMARK
 * Tujuan       : Kolom untuk tombol PROCESS di Simulation: sidik input terakhir yang diproses per WO, dan perubahan tertunda (seret) per operasi.
 * Sumber hulu  : prd_wo, prd_wo_operation (tm2prd).
 * Input        : -
 * Output       : prd_wo.process_hash/processed_at; prd_wo_operation.pending_move/pending_from/pending_reason.
 * Dipakai oleh : Ppic::needsProcess, PlanChangeService::queueMove/discardPending, PpicController::processWo.
 * Status       : Sudah dijalankan di ddt* saja; jalankan di tm2* saat rilis.
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
            if (! $s->hasColumn('prd_wo', 'process_hash')) {
                $t->string('process_hash', 40)->nullable();
                $t->dateTime('processed_at')->nullable();
            }
        });
        $s->table('prd_wo_operation', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('prd_wo_operation', 'pending_move')) {
                $t->boolean('pending_move')->default(false);
                $t->text('pending_from')->nullable();
                $t->string('pending_reason', 190)->nullable();
            }
        });
    }

    public function down(): void
    {
        $s = Schema::connection('tm2prd');
        $s->table('prd_wo', fn (Blueprint $t) => $t->dropColumn(['process_hash', 'processed_at']));
        $s->table('prd_wo_operation', fn (Blueprint $t) => $t->dropColumn(['pending_move', 'pending_from', 'pending_reason']));
    }
};
