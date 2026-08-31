<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Matrix tema (instruksi user 2026-08-31): 4 baris mode x 3 kolom corak =
// 12 kombinasi, semua data di tm2sys.sys_theme (bukan hardcode CSS/Blade)
// SUPAYA gampang diedit user langsung lewat DB ke depannya. Kolom baru
// `family` (pastel/cerah/dark/hitam) & `hue` (blue/green/red) murni buat
// pengelompokan picker UI + gampang query -- primary_color/sidebar_color/
// content_bg_color TETAP sumber kebenaran warna aktual (sama seperti
// migration 2026_08_31_000003).
//
// 7 baris ad-hoc dari migration sebelumnya (navy_dark/slate_dark/
// emerald_dark/sky_pastel/sage_pastel/blush_pastel/amber_cerah) DIHAPUS &
// digantikan matrix rapi ini -- fitur baru shipped hari yang sama, aman
// tak ada user yang sempat pilih salah satu dari 7 itu. `navy` (default,
// is_default=1) & `emerald` DIPERTAHANKAN kode-nya (jangan pecah referensi
// default) tapi di-retrofit masuk slot Cerah Biru/Cerah Hijau di matrix
// (nama + warna disesuaikan) alih-alih bikin baris duplikat. slate/indigo/
// plum/teal (di luar B/G/R) DIBIARKAN apa adanya sbg opsi "Klasik" di luar
// matrix -- tidak dihapus, cuma tak ikut grid utama.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2sys');
        $sys = DB::connection('tm2sys');

        $db->table('sys_theme', function (Blueprint $table) {
            $table->enum('family', ['pastel', 'cerah', 'dark', 'hitam'])->nullable()->after('mode')
                ->comment('Baris matrix tema. NULL = tema "Klasik" lama, tak ikut grid picker utama.');
            $table->enum('hue', ['blue', 'green', 'red'])->nullable()->after('family')
                ->comment('Kolom matrix tema (corak). NULL = tema "Klasik" lama.');
        });

        $sys->table('sys_theme')->whereIn('code', [
            'navy_dark', 'slate_dark', 'emerald_dark', 'sky_pastel', 'sage_pastel', 'blush_pastel', 'amber_cerah',
        ])->delete();

        // -- retrofit 2 tema klasik yang sudah pas masuk slot Cerah --
        $sys->table('sys_theme')->where('code', 'navy')->update([
            'name' => 'Cerah Biru (Navy)', 'family' => 'cerah', 'hue' => 'blue',
        ]);
        $sys->table('sys_theme')->where('code', 'emerald')->update([
            'name' => 'Cerah Hijau (Emerald)', 'primary_color' => '#16a34a', 'family' => 'cerah', 'hue' => 'green',
        ]);

        $sys->table('sys_theme')->insert([
            // ===== PASTEL (light, lembut, bg tinted lembut) =====
            ['code' => 'pastel_blue', 'name' => 'Pastel Biru', 'primary_color' => '#6aa3e8', 'sidebar_color' => '#1f2742', 'mode' => 'light', 'family' => 'pastel', 'hue' => 'blue', 'content_bg_color' => '#f2f6fc', 'is_default' => 0, 'activate' => 1, 'sort_order' => 110],
            ['code' => 'pastel_green', 'name' => 'Pastel Hijau', 'primary_color' => '#7fae8f', 'sidebar_color' => '#1c2e26', 'mode' => 'light', 'family' => 'pastel', 'hue' => 'green', 'content_bg_color' => '#f2f7f3', 'is_default' => 0, 'activate' => 1, 'sort_order' => 120],
            ['code' => 'pastel_red', 'name' => 'Pastel Merah', 'primary_color' => '#d98fa0', 'sidebar_color' => '#2f2028', 'mode' => 'light', 'family' => 'pastel', 'hue' => 'red', 'content_bg_color' => '#faf3f5', 'is_default' => 0, 'activate' => 1, 'sort_order' => 130],

            // ===== CERAH (light, vivid/saturated) -- Biru & Hijau retrofit di atas =====
            ['code' => 'cerah_red', 'name' => 'Cerah Merah', 'primary_color' => '#e5484d', 'sidebar_color' => '#3a1414', 'mode' => 'light', 'family' => 'cerah', 'hue' => 'red', 'content_bg_color' => null, 'is_default' => 0, 'activate' => 1, 'sort_order' => 150],

            // ===== DARK (dark, bg tinted gelap sesuai corak, aksen terang) =====
            ['code' => 'dark_blue', 'name' => 'Dark Biru', 'primary_color' => '#4c85ff', 'sidebar_color' => '#101b30', 'mode' => 'dark', 'family' => 'dark', 'hue' => 'blue', 'content_bg_color' => '#12182c', 'is_default' => 0, 'activate' => 1, 'sort_order' => 210],
            ['code' => 'dark_green', 'name' => 'Dark Hijau', 'primary_color' => '#34d399', 'sidebar_color' => '#0b1f16', 'mode' => 'dark', 'family' => 'dark', 'hue' => 'green', 'content_bg_color' => '#0f1c17', 'is_default' => 0, 'activate' => 1, 'sort_order' => 220],
            ['code' => 'dark_red', 'name' => 'Dark Merah', 'primary_color' => '#ff6b6b', 'sidebar_color' => '#2a1416', 'mode' => 'dark', 'family' => 'dark', 'hue' => 'red', 'content_bg_color' => '#1f1518', 'is_default' => 0, 'activate' => 1, 'sort_order' => 230],

            // ===== HITAM (dark, bg near-black SAMA utk semua corak, aksen jadi satu2nya pembeda) =====
            ['code' => 'hitam_blue', 'name' => 'Hitam Biru', 'primary_color' => '#5b9dff', 'sidebar_color' => '#0a0a0c', 'mode' => 'dark', 'family' => 'hitam', 'hue' => 'blue', 'content_bg_color' => '#060608', 'is_default' => 0, 'activate' => 1, 'sort_order' => 310],
            ['code' => 'hitam_green', 'name' => 'Hitam Hijau', 'primary_color' => '#2ee6a6', 'sidebar_color' => '#0a0a0c', 'mode' => 'dark', 'family' => 'hitam', 'hue' => 'green', 'content_bg_color' => '#060608', 'is_default' => 0, 'activate' => 1, 'sort_order' => 320],
            ['code' => 'hitam_red', 'name' => 'Hitam Merah', 'primary_color' => '#ff5c5c', 'sidebar_color' => '#0a0a0c', 'mode' => 'dark', 'family' => 'hitam', 'hue' => 'red', 'content_bg_color' => '#060608', 'is_default' => 0, 'activate' => 1, 'sort_order' => 330],
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_theme')->whereIn('code', [
            'pastel_blue', 'pastel_green', 'pastel_red', 'cerah_red',
            'dark_blue', 'dark_green', 'dark_red', 'hitam_blue', 'hitam_green', 'hitam_red',
        ])->delete();

        DB::connection('tm2sys')->table('sys_theme')->where('code', 'navy')->update(['name' => 'Navy (default)']);
        DB::connection('tm2sys')->table('sys_theme')->where('code', 'emerald')->update(['name' => 'Emerald', 'primary_color' => '#1f7a4d']);

        Schema::connection('tm2sys')->table('sys_theme', function (Blueprint $table) {
            $table->dropColumn(['family', 'hue']);
        });
    }
};
