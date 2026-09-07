<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Efek samping migration 2026_09_07_000004 (footer Settings/Security jadi
// data-driven lewat setupGroups): SET_THEME ("Tema") & SET_MENU ("Susunan
// Menu") -- 2 baris lama status_code=NOT_BUILT, TIDAK PERNAH punya route
// nyata di itemRoutes(), dan dulu memang sengaja tak pernah dirender di mana
// pun -- kini ikut tampil sbg leaf "not-built" (dimmed) di footer karena
// masih anak langsung SETUP. Nonaktifkan (bukan hapus, activate=0) supaya
// tampilan sidebar TIDAK berubah dari sebelumnya; keduanya tetap ada di
// tabel kalau nanti mau dibangun sungguhan (tinggal activate=1 + reparent
// ke grup yang sesuai + isi itemRoutes()).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', ['SET_THEME', 'SET_MENU'])
            ->update(['activate' => 0]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', ['SET_THEME', 'SET_MENU'])
            ->update(['activate' => 1]);
    }
};
