<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Aturan git "satu jalur, tanpa cabang" (keputusan user 2026-09-22) tidak
// dimiliki 1 menu, jadi terdaftarnya di sys_doc_reference (CLAUDE.md §6
// DEC-NAMING), bukan lewat sys_app_menu.doc_slug.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->updateOrInsert(
            ['file_name' => 'globalgitbranch.md'],
            [
                'title' => 'Aturan Git: Satu Jalur, Tanpa Cabang',
                'category' => 'GLOBAL_RULE',
                'related_menu_code' => null,
                'description' => 'Kenapa proyek ini tidak boleh punya branch atau worktree (database cuma satu), riwayat main tertinggal 357 commit, dan daftar branch yang sudah/belum boleh dihapus.',
                'sort_order' => 25,
                'activate' => 1,
            ]
        );
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'globalgitbranch.md')->delete();
    }
};
