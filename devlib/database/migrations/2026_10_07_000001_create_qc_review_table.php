<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Keputusan QC cepat per halaman / layar (user 2026-10-07): Approved atau Need improvement + catatan. Tiga lapis berurutan:
// QC (user) -> UAT_OFFICER (officer terkait) -> UAT_SUPERVISOR (atasannya); sekarang hanya QC yang dipakai.
// Riwayat: keputusan baru tidak menimpa yang lama; keputusan berlaku = baris terakhir per (item_key, stage).
// resolved_*: agen menandai catatan Need improvement sudah dikerjakan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_review', function (Blueprint $table) {
            $table->id('id_review');
            $table->string('item_key', 190);                 // slug halaman devlib (mis. p5-form-approval)
            $table->string('item_name', 190);
            $table->string('stage', 20)->default('QC');      // QC | UAT_OFFICER | UAT_SUPERVISOR
            $table->string('status', 20);                    // APPROVED | NEEDS_IMPROVEMENT
            $table->string('note', 500)->nullable();
            $table->string('reviewed_by', 60);
            $table->dateTime('reviewed_at');
            $table->string('resolved_by', 60)->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->index(['item_key', 'stage', 'id_review']);
            $table->index(['status', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_review');
    }
};
