<?php

/*
 * REMARK
 * Tujuan       : Membuang kolom sec_user.admin_theme_mode dan admin_theme_accent (tema panel admin v1/Filament); tidak ada kode yang membacanya sejak v1 dibuang.
 * Sumber hulu  : T10 tahap 7e (Jo 2026-10-22): v1 dibuang seluruhnya, aturan D.25 / D.26. Semua baris (ddtsys 159, tm2sys 169) bernilai bawaan light/blue; isi + DDL kolom tersimpan di tmerp_unused/arsip_v1_2026-10-22.zip (sec_user_admin_theme_dump_*_v1_2026-10-22.json).
 * Input        : -
 * Output       : dua kolom di sec_user pada koneksi tm2sys dihapus (mockup = ddtsys, tmerp = tm2sys).
 * Dipakai oleh : tidak ada; App\Models\Sys\SecUser tidak lagi mengisinya.
 * Status       : Aktif; idempoten; down() membuat ulang kedua kolom dengan nilai bawaan (light / blue).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = ['admin_theme_mode', 'admin_theme_accent'];

    public function up(): void
    {
        $present = array_values(array_filter(self::COLUMNS, fn (string $c) => Schema::connection('tm2sys')->hasColumn('sec_user', $c)));
        if ($present) {
            Schema::connection('tm2sys')->table('sec_user', fn (Blueprint $t) => $t->dropColumn($present));
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tm2sys');
        if (! $schema->hasColumn('sec_user', 'admin_theme_mode')) {
            $schema->table('sec_user', fn (Blueprint $t) => $t->enum('admin_theme_mode', ['light', 'pastel', 'bright', 'dark', 'black'])->default('light')->after('lang_code'));
        }
        if (! $schema->hasColumn('sec_user', 'admin_theme_accent')) {
            $schema->table('sec_user', fn (Blueprint $t) => $t->enum('admin_theme_accent', ['blue', 'green', 'red', 'purple', 'amber', 'cyan', 'mono'])->default('blue')->after('admin_theme_mode'));
        }
    }
};
