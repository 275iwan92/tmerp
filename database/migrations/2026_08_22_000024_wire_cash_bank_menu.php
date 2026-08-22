<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Jurnal Kas & Bank (BKM/BKK/BBM/BBK) dibangun 2026-08-22 dari mockup HTML
// user. Placeholder menu FIN_CASH "Kasir (Kas/Bank)" (id 611) sudah ada sejak
// draf awal, tinggal dipindah NOT_BUILT -> READY_FOR_QC (pola sama spt
// PROC_PQ/PROC_RET di migration 2026_08_22_000017).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'FIN_CASH')->update(['status_code' => 'READY_FOR_QC']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'FIN_CASH')->update(['status_code' => 'NOT_BUILT']);
    }
};
