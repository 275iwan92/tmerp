<?php

/*
 * REMARK
 * Tujuan       : Tabel ML urutan job: catatan susunan officer (prd_plan_trace) dan model hasil latih (prd_sequence_model).
 * Sumber hulu  : PROCESS (TRIAL) dan APPLY TO LIVE SCHEDULE (FINAL) di Form WO; App\Services\Scheduling\Sequence.
 * Input        : -
 * Output       : prd_plan_trace (satu baris per operasi per snapshot per mesin), prd_sequence_model (bobot, versi, status ACTIVE/CANDIDATE/FROZEN).
 * Dipakai oleh : TraceRecorder, SequenceTrainer, SequenceSuggester, perintah sequence:train.
 * Status       : ddt* dijalankan sesi; tm2* oleh Jo. Relasi lintas-DB logis tanpa FK. down() membuang kedua tabel.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('tm2prd');
        if (! $s->hasTable('prd_plan_trace')) {
            $s->create('prd_plan_trace', function (Blueprint $t) {
                $t->id('id_plan_trace');
                $t->unsignedBigInteger('snapshot_wo_id');          // the Work Order whose PROCESS / APPLY made this snapshot
                $t->string('snapshot_key', 60)->index();           // one snapshot of one machine (TRIAL: wo+machine, FINAL: run+wo+machine)
                $t->unsignedBigInteger('wo_id');
                $t->unsignedBigInteger('wo_operation_id');
                $t->unsignedBigInteger('machine_id');
                $t->unsignedBigInteger('process_id')->nullable();
                $t->string('status', 10)->index();                 // TRIAL (context only) | FINAL (training label)
                $t->string('process_hash', 64)->nullable();
                $t->unsignedInteger('engine_pos');
                $t->unsignedInteger('officer_pos');
                $t->json('features')->nullable();
                $t->string('pull_reason', 190)->nullable();
                $t->string('machine_trouble', 190)->nullable();
                $t->boolean('from_suggestion')->default(false);
                $t->unsignedBigInteger('run_id')->nullable();
                $t->timestamps();
                $t->index(['snapshot_wo_id', 'machine_id', 'status'], 'ix_plan_trace_snapshot');
            });
        }
        if (! $s->hasTable('prd_sequence_model')) {
            $s->create('prd_sequence_model', function (Blueprint $t) {
                $t->id('id_sequence_model');
                $t->unsignedInteger('version');
                $t->string('status', 10)->index();                 // ACTIVE | CANDIDATE | FROZEN
                $t->json('weights');
                $t->json('feature_names');
                $t->unsignedInteger('pairs_count')->default(0);
                $t->unsignedInteger('arrangements_count')->default(0);
                $t->decimal('holdout_accuracy_model', 6, 4)->nullable();
                $t->decimal('holdout_accuracy_engine', 6, 4)->nullable();
                $t->timestamp('trained_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->dropIfExists('prd_sequence_model');
        Schema::connection('tm2prd')->dropIfExists('prd_plan_trace');
    }
};
