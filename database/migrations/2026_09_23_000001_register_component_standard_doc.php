<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/componentstandard.md (2026-09-23) -- standar komponen UI OOP, fondasi
// semua standar UI lain. Tidak dimiliki 1 menu -> sys_doc_reference (CLAUDE.md §6).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->updateOrInsert(
            ['file_name' => 'componentstandard.md'],
            [
                'title' => 'Standar Komponen UI (OOP)',
                'category' => 'STANDARD',
                'related_menu_code' => null,
                'description' => 'Token warna + primitive x-ui.* (class PHP turunan UiComponent) yang diwarisi semua komponen & layar; ratchet test.',
                'sort_order' => 5,
                'activate' => 1,
            ]
        );
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'componentstandard.md')->delete();
    }
};
