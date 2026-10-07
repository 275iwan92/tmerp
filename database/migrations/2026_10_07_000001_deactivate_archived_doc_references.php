<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Dokumen yang diarsipkan user 2026-10-07 (docs/usang_untuk_dipindah): baris daftar dokumennya dinonaktifkan,
// bukan dihapus, supaya bisa diaktifkan lagi kalau berkasnya dipulihkan.
return new class extends Migration
{
    private const FILES = [
        'checklistmockuperp.md',
        'security_brief.md',
        'themestandard.md',
        'catatanauditmenyeluruh.md',
    ];

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->whereIn('file_name', self::FILES)->update(['activate' => 0]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->whereIn('file_name', self::FILES)->update(['activate' => 1]);
    }
};
