<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Sejak layouts::report dibangun (sidebar navy meniru mockup, sudah punya
// nav lengkap 12 item + logout sendiri), grup "Laporan" di menu MGMT lama
// (MGMT_SALES/PURCHASE/ACCOUNTING/PRODUCTION/HR/WAREHOUSE) jadi DUPLIKAT
// navigasi -- user yg juga punya modul lain (mis. admin) melihat 2 jalan
// masuk berbeda ke laporan yang sama di menu Operational, membingungkan
// (keluhan user 2026-09-13 "kenapa di operational ada menu laporan
// management"). Satu-satunya pintu masuk yang perlu tersisa di menu
// Operational: "Dashboard Eksekutif" (MGMT_EXEC) -> route('report.executive')
// -- begitu dibuka, navigasi selanjutnya 100% ditangani sidebar baru.
// Route report.* TIDAK dihapus (masih dipakai sidebar baru), hanya entri
// menu lama yang dinonaktifkan (bukan delete -- reversibel).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')
            ->whereIn('code', [
                'MGMT_LAPORAN',
                'MGMT_SALES',
                'MGMT_PURCHASE',
                'MGMT_ACCOUNTING',
                'MGMT_PRODUCTION',
                'MGMT_HR',
                'MGMT_WAREHOUSE',
            ])
            ->update(['activate' => false]);

        $db->table('sys_app_menu')
            ->where('code', 'MGMT_EXEC')
            ->update(['label' => 'Buka Laporan Manajemen']);
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')
            ->whereIn('code', [
                'MGMT_LAPORAN',
                'MGMT_SALES',
                'MGMT_PURCHASE',
                'MGMT_ACCOUNTING',
                'MGMT_PRODUCTION',
                'MGMT_HR',
                'MGMT_WAREHOUSE',
            ])
            ->update(['activate' => true]);

        $db->table('sys_app_menu')
            ->where('code', 'MGMT_EXEC')
            ->update(['label' => 'Dashboard Eksekutif']);
    }
};
