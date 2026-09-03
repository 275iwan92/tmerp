<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-09-03: sidebar kiri "Configuration" diganti nama
// jadi "Settings" (Security TETAP tidak berubah) -- nama "Configuration"
// dibebaskan supaya bisa dipakai grup baru di dropdown profil (Tema+
// Bahasa+Portal Karyawan+Aktivitas saya digabung 1 grup collapsible,
// main-menu.blade.php). Route/kode (SET_CONFIG, setup.configuration)
// TIDAK berubah, cuma label tampilan (dipakai jadi judul tab saat dibuka).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'SET_CONFIG')
            ->update(['label' => 'Settings']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'SET_CONFIG')
            ->update(['label' => 'Configuration']);
    }
};
