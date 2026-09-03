<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// GAP KRITIS ditemukan 2026-09-03 (lihat CLAUDE.md §4 "VITAL 3 ENGINE" +
// memory feedback_prodev_must_pull_from_inquiry_new.md): Inquiry Mode #1
// (BARU) HARUS bisa kirim iqp_part -> ProDev, tapi 0 link pernah dibangun.
// Referensi by-ID lintas-DB tm2sdi->tm2mst, BUKAN FK fisik (aturan CLAUDE.md
// §2), sama pola dgn iqp_costing_part.prodev_part_id yang sudah ada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_part', function (Blueprint $table): void {
            $table->unsignedBigInteger('prod_part_id')->nullable()->after('id_part');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_part', function (Blueprint $table): void {
            $table->dropColumn('prod_part_id');
        });
    }
};
