<?php

/*
 * REMARK
 * Tujuan       : Catatan kutipan yang sudah diterima tiap user per slot (pagi / setelah makan siang / sore-malam) agar tidak ada kutipan sama dalam 7 hari.
 * Sumber hulu  : tm2sys.sys_quotes (pool kutipan) dan tm2sys.sec_user.
 * Input        : -
 * Output       : Tabel sys_quote_shown; UNIQUE (id_user, shown_on, slot) = satu kutipan per slot per hari per user.
 * Dipakai oleh : App\Services\Sys\QuoteRotation (widget Quote of the Day).
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tm2sys');
        if ($schema->hasTable('sys_quote_shown')) {
            return;
        }
        $schema->create('sys_quote_shown', function (Blueprint $t) {
            $t->id('id_quote_shown');
            $t->unsignedBigInteger('id_user');
            $t->unsignedBigInteger('id_quote');
            $t->string('slot', 10);
            $t->date('shown_on');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['id_user', 'shown_on', 'slot']);
            $t->index(['id_user', 'id_quote']);
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->dropIfExists('sys_quote_shown');
    }
};
