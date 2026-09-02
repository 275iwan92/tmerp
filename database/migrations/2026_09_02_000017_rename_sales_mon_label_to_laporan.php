<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/BACKLOG.md item "mudah" #3 -- seragamkan label grup laporan Sales
// (SALES_MON) ke "Laporan" polos, sama spt Inventory & modul lain sudah
// diseragamkan sejak 2026-08-22 (docs/sidebarmenustandard.md §1, catatan
// "belum disentuh, evaluasi lagi kalau modul lain sudah konsisten" --
// sekarang giliran Sales).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'SALES_MON')
            ->update(['label' => 'Laporan']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'SALES_MON')
            ->update(['label' => 'Monitoring & Laporan']);
    }
};
