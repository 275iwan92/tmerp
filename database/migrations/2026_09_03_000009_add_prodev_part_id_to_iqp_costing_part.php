<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BACKLOG.md §0 "Alokasi biaya proporsional prod_layout_group multi-part".
// Referensi by-ID saja (BUKAN FK fisik -- lintas DB tm2sdi->tm2mst, lihat
// CLAUDE.md §2 aturan lintas-DB): begitu prod_layout_group menaungi >1 part
// (Tool #10), officer HARUS pilih part mana dari group itu yang direpresentasikan
// baris costing ini -- disimpan di sini supaya picker UI sticky saat layar
// dibuka ulang (bukan wajib utk kalkulasi itu sendiri, hasil alokasi sudah
// dipersist ke i_cut_per_plano existing -- ini murni utk UX).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table): void {
            $table->unsignedBigInteger('prodev_part_id')->nullable()->after('prod_layout_group_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table): void {
            $table->dropColumn('prodev_part_id');
        });
    }
};
