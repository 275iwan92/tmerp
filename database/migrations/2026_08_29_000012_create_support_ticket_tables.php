<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fitur "Support" (dropdown profil, isi placeholder "Bantuan & dukungan" yg
// tadinya cuma toast "segera") -- instruksi user 2026-08-29: versi in-app dari
// docs/qc_feedback.md, model ticketing (auto tanggal, deskripsi, attach
// gambar, submit). Kedua channel (file .md & tiket in-app) sengaja dibiarkan
// hidup berdampingan, bukan disatukan -- .md cocok utk catatan cepat/batch,
// tiket in-app cocok utk 1 laporan spesifik + lampiran + status terlacak.
// Sama DB (tm2sys) dgn sec_user -> FK fisik aman.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2sys');

        $db->create('sys_support_ticket', function (Blueprint $table) {
            $table->bigIncrements('id_ticket');
            $table->string('ticket_no', 20)->unique()->comment('Format SPT{YY}{MM}{NNNN}, digenerate app');
            $table->unsignedBigInteger('created_by_user_id');
            $table->string('screen_context', 150)->nullable()->comment('Label tab aktif saat submit (auto)');
            $table->text('description');
            $table->string('status', 20)->default('OPEN')->comment('OPEN|IN_PROGRESS|DONE|CLOSED -- selaras qc_feedback.md');
            $table->text('resolution_note')->nullable();
            $table->string('resolved_by', 100)->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id_user')->on('sec_user');
            $table->index('status', 'idx_ticket_status');
        });

        $db->create('sys_support_ticket_attachment', function (Blueprint $table) {
            $table->bigIncrements('id_attachment');
            $table->unsignedBigInteger('ticket_id');
            $table->string('file_path', 255);
            $table->dateTime('uploaded_at');

            $table->foreign('ticket_id')->references('id_ticket')->on('sys_support_ticket')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $db = Schema::connection('tm2sys');
        $db->dropIfExists('sys_support_ticket_attachment');
        $db->dropIfExists('sys_support_ticket');
    }
};
