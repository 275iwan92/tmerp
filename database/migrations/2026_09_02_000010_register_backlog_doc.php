<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan BACKLOG.md (docs root, bukan docs/uistandardindex.md -- ini
// bukan standar UI, tapi tracking lintas-modul, pola sama dgn docs/qc_feedback.md
// yang juga tidak masuk index UI standar). CLAUDE.md §6: sys_doc_reference
// sumber kebenarannya migration.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_doc_reference')->where('file_name', 'BACKLOG.md')->exists()) {
            return;
        }

        $sys->table('sys_doc_reference')->insert([
            'file_name' => 'BACKLOG.md',
            'title' => 'Backlog Lintas-Modul (Fase 2 Report + Semua Item Tertunda)',
            'category' => 'TRACKING',
            'related_menu_code' => null,
            'description' => 'Gabungan semua pekerjaan direncanakan tapi belum dikerjakan -- Fase 2 Report 4-level + item lama tiap modul. Verifikasi ulang ke kode sebelum eksekusi.',
            'sort_order' => 5,
            'activate' => true,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'BACKLOG.md')->delete();
    }
};
