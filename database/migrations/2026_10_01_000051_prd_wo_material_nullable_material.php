<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sejak 2026-09-29 Sales hanya memilih Jenis + Gramatur bahan di Inquiry (tanpa merek) -> BOM WO untuk kertas belum punya
// material_id. WoGenerationService sengaja tetap membuat barisnya (qty 0) supaya PPIC melengkapi, tapi kolom NOT NULL membuat
// Post to Production / Generate WO gagal total. material_id jadi boleh kosong = "bahan belum dipilih".
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2prd')->table('prd_wo_material', fn (Blueprint $t) => $t->unsignedBigInteger('material_id')->nullable()->change());
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->table('prd_wo_material', fn (Blueprint $t) => $t->unsignedBigInteger('material_id')->nullable(false)->change());
    }
};
