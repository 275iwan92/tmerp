<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kerangka i18n app-wide (keputusan user 2026-09-07) -- preferensi bahasa
// PER USER, dibaca App\Support\Localization\Translator. Default 'id' (sama
// spt sys_languages.is_default saat ini). BUKAN kolom baru bertipe enum
// terbatas (id/en) -- validasi kode valid dilakukan di service layer thd
// SysLanguage::pluck('code'), supaya nambah bahasa baru (lewat Settings >
// Language) tidak perlu migration kolom lagi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $table): void {
            $table->string('lang_code', 5)->default('id')->after('theme_code');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $table): void {
            $table->dropColumn('lang_code');
        });
    }
};
