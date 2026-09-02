<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Preferensi warna dashboard Filament /admin -- PERSONAL per-user (docs/mockups/
// mockup_dashboardcolor.html), TERPISAH dari sec_user.theme_code (itu punya
// shell lama, matrix 4x3, lihat themestandard.md). Kolom baru krn semantiknya
// beda (5 mode x 7 aksen, cuma berlaku di panel Filament).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $table) {
            $table->enum('admin_theme_mode', ['light', 'pastel', 'bright', 'dark', 'black'])->default('light')->after('theme_code');
            $table->enum('admin_theme_accent', ['blue', 'green', 'red', 'purple', 'amber', 'cyan', 'mono'])->default('blue')->after('admin_theme_mode');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $table) {
            $table->dropColumn(['admin_theme_mode', 'admin_theme_accent']);
        });
    }
};
