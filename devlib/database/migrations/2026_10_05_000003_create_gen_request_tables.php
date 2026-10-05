<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Generator devlib.test (user 2026-10-05): permintaan pembuatan layar yang ditulis user dengan kata-katanya sendiri -- nama,
// deskripsi, tipe, perlu engine + penjelasannya, alur data / dokumen. Agen membacanya, mengisi pratinjau (objek yang dibutuhkan,
// input / output engine, formula) dan baru membangun setelah user menyetujui. Objek DevLib yang belum ada = satu baris
// gen_request_object yang harus disetujui user dulu.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gen_request', function (Blueprint $table) {
            $table->id('id_request');
            $table->string('request_no', 20)->unique();          // RQ-0001
            $table->string('name', 150);
            $table->text('description');
            $table->string('request_type', 20);                  // MASTER | TRANSACTIONAL | CUSTOM
            $table->boolean('need_engine')->default(false);
            $table->text('engine_description')->nullable();
            $table->text('flow_description')->nullable();
            // Pratinjau: diisi agen, boleh dikoreksi user (formula terutama).
            $table->text('engine_input')->nullable();
            $table->text('engine_output')->nullable();
            $table->text('engine_formula')->nullable();
            $table->text('agent_note')->nullable();              // pertanyaan / catatan agen untuk user
            $table->string('status', 20)->default('REQUESTED');  // REQUESTED | QUESTION | PREVIEW | APPROVED | BUILT | CANCELLED
            $table->string('agent', 20)->nullable();             // iwan | jo
            $table->string('screen_label', 190)->nullable();     // Sales > Master > Delivery Method
            $table->string('screen_url', 255)->nullable();
            $table->string('requested_by', 60);
            $table->string('approved_by', 60)->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('built_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('gen_request_object', function (Blueprint $table) {
            $table->id('id_object');
            $table->foreignId('request_id')->constrained('gen_request', 'id_request')->cascadeOnDelete();
            $table->string('object_name', 150);                  // Grid Master, Sheet size field (max / min, cm)
            $table->string('object_tag', 120)->nullable();       // x-dl::grid; null = belum ada
            $table->boolean('is_built')->default(true);          // false = objek belum ada di DevLib
            $table->string('decision', 20)->default('NONE');     // NONE (sudah ada) | PENDING | APPROVED | REJECTED
            $table->string('decided_by', 60)->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gen_request_object');
        Schema::dropIfExists('gen_request');
    }
};
