<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Logo bendera" negara (keputusan user 2026-09-02) -- geo_country SUDAH ada
// & sudah relasi ke fin_currency (currency_id), jadi TIDAK bikin tabel country
// baru (hindari duplikasi) -- cukup tambah 1 kolom. Emoji flag (bukan file
// gambar) -- 0 asset/storage baru dibutuhkan, konsisten dgn pola flag emoji
// yang sudah dipakai di tempat lain (mis. UIUX_components_part1.html phone
// country selector).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('geo_country', function (Blueprint $table) {
            $table->string('flag_emoji', 8)->nullable()->after('iso_code');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('geo_country', function (Blueprint $table) {
            $table->dropColumn('flag_emoji');
        });
    }
};
