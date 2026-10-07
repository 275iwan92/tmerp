<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Agent History devlib.test (user 2026-10-07): catatan pengingat user. Ringkasan laporan agen Iwan / Jo, keputusan,
// catatan, dan tautan Artifact disimpan di sini supaya bisa dibaca lagi. Diisi agen lewat `php artisan note:add`
// atau user lewat form (App\Support\AgentHistory).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_note', function (Blueprint $table) {
            $table->id('id_note');
            $table->string('author', 20);                 // iwan | jo | user
            $table->string('kind', 20);                   // REPORT | NOTE | DECISION | ARTIFACT
            $table->string('title', 190);
            $table->text('body')->nullable();
            $table->string('url', 500)->nullable();       // tautan Artifact / dokumen
            $table->boolean('pinned')->default(false);
            $table->dateTime('read_at')->nullable();      // null = belum dibaca user
            $table->string('created_by', 60);
            $table->timestamps();
            $table->index(['author', 'kind']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_note');
    }
};
