<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// sys_support_replies sebelumnya dibuat manual (bukan lewat migration) dgn FK
// salah target: `sys_support_tickets` (plural, tabel legacy kosong, tak
// dipakai kode manapun) alih-alih `sys_support_ticket` (singular, tabel asli
// dipakai SysSupportTicket model, lihat 2026_08_29_000012). Ditemukan saat
// fitur reply thread digarap 2026-08-31 (INSERT gagal FK constraint). Kedua
// tabel kosong (0 baris) jadi aman drop+recreate.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2sys');

        $db->dropIfExists('sys_support_replies');
        $db->dropIfExists('sys_support_tickets');

        $db->create('sys_support_replies', function (Blueprint $table) {
            $table->bigIncrements('id_reply');
            $table->unsignedBigInteger('ticket_id');
            $table->string('author', 100)->comment('sec_user.login/name pembalas -- staff atau candidate sendiri');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('ticket_id')->references('id_ticket')->on('sys_support_ticket')->onDelete('cascade');
            $table->index('ticket_id', 'idx_reply_ticket');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->dropIfExists('sys_support_replies');
    }
};
