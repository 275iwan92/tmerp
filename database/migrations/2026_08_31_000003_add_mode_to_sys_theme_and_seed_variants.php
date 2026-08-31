<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tema terang/gelap (instruksi user 2026-08-31: "semua user bisa
// menggunakannya, termasuk tema berwarna pastel/cerah/dark") disatukan ke
// sys_theme yang sudah ada (bukan sumbu terpisah) -- pilih 1 baris = dapat
// hue + mode sekaligus, dipilih via sec_user.theme_code yg sudah ada.
// sidebar/topbar SENGAJA selalu gelap di semua baris (termasuk baris
// "light") -- teks topbar hardcode terang (lihat .mega-item di app.css),
// kalau sidebar dibikin pastel/terang, teks itu jadi tak terbaca. Yang
// berubah antar mode cuma token area konten (content_bg dkk, lihat
// partials/theme-vars.blade.php).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2sys');

        $db->table('sys_theme', function (Blueprint $table) {
            $table->enum('mode', ['light', 'dark'])->default('light')->after('sidebar_color');
            $table->string('content_bg_color', 9)->nullable()->after('mode')
                ->comment('Override warna latar area konten. NULL = pakai default per mode (light/dark).');
        });

        DB::connection('tm2sys')->table('sys_theme')->insert([
            // -- varian gelap, 1 per hue existing yg paling sering dipakai --
            ['code' => 'navy_dark', 'name' => 'Navy Gelap', 'primary_color' => '#4c85ff', 'sidebar_color' => '#0c1022', 'mode' => 'dark', 'content_bg_color' => null, 'is_default' => 0, 'activate' => 1, 'sort_order' => 15],
            ['code' => 'slate_dark', 'name' => 'Slate Gelap', 'primary_color' => '#8b93bd', 'sidebar_color' => '#14161f', 'mode' => 'dark', 'content_bg_color' => null, 'is_default' => 0, 'activate' => 1, 'sort_order' => 35],
            ['code' => 'emerald_dark', 'name' => 'Emerald Gelap', 'primary_color' => '#34d399', 'sidebar_color' => '#0b1f16', 'mode' => 'dark', 'content_bg_color' => null, 'is_default' => 0, 'activate' => 1, 'sort_order' => 55],
            // -- pastel (lembut, tetap mode terang) --
            ['code' => 'sky_pastel', 'name' => 'Sky Pastel', 'primary_color' => '#6aa3e8', 'sidebar_color' => '#1f2742', 'mode' => 'light', 'content_bg_color' => '#f2f6fc', 'is_default' => 0, 'activate' => 1, 'sort_order' => 12],
            ['code' => 'sage_pastel', 'name' => 'Sage Pastel', 'primary_color' => '#7fae8f', 'sidebar_color' => '#1c2e26', 'mode' => 'light', 'content_bg_color' => '#f2f7f3', 'is_default' => 0, 'activate' => 1, 'sort_order' => 22],
            ['code' => 'blush_pastel', 'name' => 'Blush Pastel', 'primary_color' => '#d98fa0', 'sidebar_color' => '#2f2028', 'mode' => 'light', 'content_bg_color' => '#faf3f5', 'is_default' => 0, 'activate' => 1, 'sort_order' => 32],
            // -- cerah (vivid, mode terang) --
            ['code' => 'amber_cerah', 'name' => 'Amber Cerah', 'primary_color' => '#d9822b', 'sidebar_color' => '#2b1f10', 'mode' => 'light', 'content_bg_color' => null, 'is_default' => 0, 'activate' => 1, 'sort_order' => 70],
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_theme')->whereIn('code', [
            'navy_dark', 'slate_dark', 'emerald_dark', 'sky_pastel', 'sage_pastel', 'blush_pastel', 'amber_cerah',
        ])->delete();

        Schema::connection('tm2sys')->table('sys_theme', function (Blueprint $table) {
            $table->dropColumn(['mode', 'content_bg_color']);
        });
    }
};
