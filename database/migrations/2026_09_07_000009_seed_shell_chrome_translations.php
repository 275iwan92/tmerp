<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Kerangka i18n app-wide (keputusan user 2026-09-07): set awal key terjemahan
// utk string chrome shell PALING sering muncul (App\Support\Localization\Translator,
// dipakai main-menu.blade.php) -- lihat docblock Translator soal cakupan
// yang SENGAJA belum penuh (baru shell, belum semua layar transaksi).
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['shell_home', 'Home', 'Home'],
            ['shell_logout', 'Keluar aplikasi', 'Log out'],
            ['shell_theme', 'Tema', 'Theme'],
            ['shell_language', 'Bahasa', 'Language'],
            ['shell_classic', 'Klasik', 'Classic'],
            ['shell_section_pesan_info', 'Pesan & info', 'Messages & Info'],
            ['shell_section_akun', 'Akun', 'Account'],
            ['shell_section_configuration', 'Configuration', 'Configuration'],
        ];

        $sys = DB::connection('tm2sys');

        foreach ($rows as [$key, $id, $en]) {
            $sys->table('sys_translations')->updateOrInsert(['lang_code' => 'id', 'trans_key' => $key], ['trans_value' => $id]);
            $sys->table('sys_translations')->updateOrInsert(['lang_code' => 'en', 'trans_key' => $key], ['trans_value' => $en]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $keys = ['shell_home', 'shell_logout', 'shell_theme', 'shell_language', 'shell_classic', 'shell_section_pesan_info', 'shell_section_akun', 'shell_section_configuration'];
        $sys->table('sys_translations')->whereIn('trans_key', $keys)->delete();
    }
};
