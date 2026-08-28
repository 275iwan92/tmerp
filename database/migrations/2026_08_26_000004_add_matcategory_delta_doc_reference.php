<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan docs/matcategory_delta.md ke sys_doc_reference (DEC-NAMING
// 2026-08-26) -- dokumen keputusan penamaan+desain mat_category (pengganti
// doc_rmprefix1..5), terkait menu MD_MATCAT. Sort_order 141, tepat setelah
// itemmaster_delta.md (140) karena topiknya bersinggungan (item bahan baku).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_doc_reference')->where('file_name', 'matcategory_delta.md')->exists()) {
            return;
        }

        $sys->table('sys_doc_reference')->insert([
            'file_name' => 'matcategory_delta.md',
            'title' => 'Delta Master Kategori Bahan Baku — Pengganti doc_rmprefix1..5',
            'category' => 'REFERENCE',
            'related_menu_code' => 'MD_MATCAT',
            'sort_order' => 141,
            'is_active' => true,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'matcategory_delta.md')->delete();
    }
};
