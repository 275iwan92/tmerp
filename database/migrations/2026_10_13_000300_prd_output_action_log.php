<?php

/*
 * REMARK
 * Tujuan       : Log aksi layar Production Output: setiap Play/Pause/Stop dan SIMPAN HASIL tercatat kapan, siapa, dari host mana, apa yang berubah.
 * Sumber hulu  : Diisi OutputActionLog::record() di dalam transaksi aksi yang sama (OperatorTimerService).
 * Input        : -
 * Output       : Tabel tm2prd.prd_output_action_log (hanya tambah baris, tanpa ubah/hapus); Stop timer dibaca dari tabel ini.
 * Dipakai oleh : OperatorTimerService, App\Support\V2\Data\ProductionOutput.
 * Status       : Disetujui Jo 13 Okt 2026; dijalankan di ddt* saja, jalankan di tm2* saat rilis.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('tm2prd');
        if ($s->hasTable('prd_output_action_log')) {
            return;
        }
        $s->create('prd_output_action_log', function (Blueprint $t) {
            $t->id('log_id');
            $t->unsignedBigInteger('operation_id');
            $t->unsignedBigInteger('machine_id')->nullable();
            $t->string('action', 20);            // SETUP_PLAY SETUP_PAUSE SETUP_STOP RUN_PLAY RUN_PAUSE RUN_STOP SAVE_RESULT
            $t->json('detail')->nullable();      // {"before": ..., "after": ...}
            $t->string('acted_by', 50);          // sec_user.login
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->dateTime('acted_at');
            $t->string('client_ip', 45)->nullable();
            $t->string('client_host', 190)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->index(['operation_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->dropIfExists('prd_output_action_log');
    }
};
