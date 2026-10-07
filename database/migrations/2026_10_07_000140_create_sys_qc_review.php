<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Keputusan QC cepat per layar (user 2026-10-07): Approved / Need improvement + catatan, tiga tahap berurutan
// QC (user) -> UAT_OFFICER (officer terkait) -> UAT_SUPERVISOR (atasannya); ketiganya Approved = siap rilis ke live.
// Riwayat tidak menimpa; keputusan berlaku = baris terakhir per (item_key, stage). item_key = slug layar v2.
// Dijalankan di situs UAT (php artisan migrate --env=mockup) -- bukan tm2sys live.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        Schema::connection('tm2sys')->create('sys_qc_review', function (Blueprint $table) {
            $table->id('id_review');
            $table->string('item_key', 190);
            $table->string('item_name', 190);
            $table->string('stage', 20)->default('QC');
            $table->string('status', 20);
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
        Schema::connection('tm2sys')->dropIfExists('sys_qc_review');
    }
};
