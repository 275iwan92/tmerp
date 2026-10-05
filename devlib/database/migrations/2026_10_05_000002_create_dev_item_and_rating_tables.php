<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Executive Summary devlib.test (user 2026-10-05): daftar hasil kerja (form / grid / report / engine / objek / fitur)
// dan penilaian user atasnya. dev_item diisi dari resources/data/v2/build.json (App\Support\Summary::sync);
// dev_rating = riwayat (penilaian baru tidak menimpa yang lama).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_item', function (Blueprint $table) {
            $table->id('id_item');
            $table->string('item_type', 20);              // FORM | GRID | REPORT | DASHBOARD | ENGINE | OBJECT | FEATURE
            $table->string('item_key', 190)->unique();    // menu:<code> | engine:<key> | object:<class> | feature:<text>
            $table->string('name', 190);
            $table->string('module', 120)->nullable();
            $table->string('qc_status', 20)->nullable();  // NOT_BUILT | V1 | READY_FOR_QC | LOCKED
            $table->string('built_by', 20)->nullable();   // iwan | jo | null = sebelum pelacakan
            $table->date('built_at')->nullable();
            $table->string('changed_by', 20)->nullable();
            $table->date('changed_at')->nullable();
            $table->boolean('activate')->default(true);   // false = sudah tidak ada di build.json
            $table->timestamps();
            $table->index(['item_type', 'module']);
        });

        Schema::create('dev_rating', function (Blueprint $table) {
            $table->id('id_rating');
            $table->foreignId('item_id')->constrained('dev_item', 'id_item');
            $table->unsignedTinyInteger('rating');        // 1..5
            $table->string('note', 500)->nullable();
            $table->string('agent', 20)->nullable();      // agen yang dinilai = pengubah terakhir saat itu
            $table->string('rated_by', 60);
            $table->dateTime('rated_at');
            $table->index(['item_id', 'rated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_rating');
        Schema::dropIfExists('dev_item');
    }
};
