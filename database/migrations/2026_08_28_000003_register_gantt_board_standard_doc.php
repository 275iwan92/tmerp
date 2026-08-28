<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/ganttboardstandard.md -- standar komponen Gantt reusable (resources/views/
// components/gantt/*), diekstrak dari Scheduling Board 2026-08-28. Didaftarkan
// di sys_doc_reference (bukan doc_slug per-menu) karena lintas-modul, pola sama
// griduistandard.md/sidebarmenustandard.md (CLAUDE.md §6).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if (! $sys->table('sys_doc_reference')->where('file_name', 'ganttboardstandard.md')->exists()) {
            $sys->table('sys_doc_reference')->insert([
                'file_name' => 'ganttboardstandard.md',
                'title' => 'Standar Komponen Gantt Board (Reusable)',
                'category' => 'STANDARD',
                'related_menu_code' => null,
                'description' => 'Kontrak Row/Block VM, props <x-gantt.board>, dan Alpine store gantt utk semua layar Gantt (mesin/waktu) ke depan.',
                'sort_order' => 45,
                'activate' => true,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'ganttboardstandard.md')->delete();
    }
};
