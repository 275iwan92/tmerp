<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/printnotastandard.md -- standar komponen cetak Nota reusable
// (resources/views/components/print/*), diekstrak dari inquiry/nota.blade.php
// + quotation/print.blade.php 2026-08-28. Didaftarkan di sys_doc_reference
// (bukan doc_slug per-menu) karena lintas-modul, pola sama griduistandard.md.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if (! $sys->table('sys_doc_reference')->where('file_name', 'printnotastandard.md')->exists()) {
            $sys->table('sys_doc_reference')->insert([
                'file_name' => 'printnotastandard.md',
                'title' => 'Standar Dokumen Cetak Nota (Reusable)',
                'category' => 'STANDARD',
                'related_menu_code' => null,
                'description' => 'Komponen print.document/sheet/header/parties/section/signatures/footer + kontrak tema warna utk semua dokumen cetak nota (kop+parties+signature) ke depan.',
                'sort_order' => 46,
                'activate' => true,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'printnotastandard.md')->delete();
    }
};
