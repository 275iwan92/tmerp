<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Laporan "Hasil Quotation (WIN/LOST)" ditiadakan (user 2026-10-02): hasil WIN / LOST / Cancel cukup di layar Quotation
// sendiri (filter status + kolom Closed, Customer Order, Reason). Layarnya dipindah ke arsip unused.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SALES_RPT_QUORES')->delete();
    }

    public function down(): void
    {
        // Layar sudah diarsipkan; barisnya tidak dibuat ulang.
    }
};
