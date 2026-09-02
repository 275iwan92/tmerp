<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Format angka regional per bahasa (keputusan user 2026-09-02) -- Indonesia
// titik-ribuan/koma-desimal ("1.234,56"), Inggris koma-ribuan/titik-desimal
// ("1,234.56"). Ditaruh di sys_languages (bukan tabel country) karena selector
// bahasa aplikasi ini cuma 2 kode datar (id/en), bukan locale penuh per negara.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sys')->table('sys_languages', function (Blueprint $table) {
            $table->string('decimal_separator', 1)->default(',')->after('name');
            $table->string('thousand_separator', 1)->default('.')->after('decimal_separator');
        });

        DB::connection('tm2sys')->table('sys_languages')->where('code', 'en')->update([
            'decimal_separator' => '.',
            'thousand_separator' => ',',
        ]);
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->table('sys_languages', function (Blueprint $table) {
            $table->dropColumn(['decimal_separator', 'thousand_separator']);
        });
    }
};
