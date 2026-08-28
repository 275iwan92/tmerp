<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan docs/topmanagementreport_tier1.md ke sys_doc_reference (bukan
// sys_app_menu.doc_slug -- dokumen ini lintas-modul (Sales/Procurement/
// Inventory/Finance/HR), bukan milik 1 menu item, sesuai CLAUDE.md §6.
// Kategori ENGINE, pola sama dgn flowengine_tier1..4.md -- laporan ini
// BELUM digarap, murni dokumen perencanaan (keputusan user 2026-08-27).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->insert([
            'file_name' => 'topmanagementreport_tier1.md',
            'title' => 'Laporan Top Management — Konsep & Cakupan (Tier 1)',
            'category' => 'ENGINE',
            'related_menu_code' => null,
            'description' => null,
            'sort_order' => 95,
            'activate' => true,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'topmanagementreport_tier1.md')->delete();
    }
};
