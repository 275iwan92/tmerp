<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan docs/fieldrename_state.yaml ke sys_doc_reference (DEC-NAMING) --
// pelacak progres DEC-FIELDNAME-STATUS (is_active->activate dkk, keputusan
// user 2026-08-26), dokumen lintas-modul jadi tanpa related_menu_code.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_doc_reference')->where('file_name', 'fieldrename_state.yaml')->exists()) {
            return;
        }

        $sys->table('sys_doc_reference')->insert([
            'file_name' => 'fieldrename_state.yaml',
            'title' => 'Progres Rename is_active/is_confirmed (DEC-FIELDNAME-STATUS)',
            'category' => 'GLOBAL_RULE',
            'related_menu_code' => null,
            'sort_order' => 61,
            'is_active' => true,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'fieldrename_state.yaml')->delete();
    }
};
