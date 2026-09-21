<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Dokumen aturan global tidak dimiliki 1 menu, jadi terdaftarnya di
// sys_doc_reference (CLAUDE.md §6 DEC-NAMING), bukan lewat sys_app_menu.doc_slug.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->updateOrInsert(
            ['file_name' => 'usersecuritystandard.md'],
            [
                'title' => 'Standar Identitas & Keamanan Tiap User',
                'category' => 'SECURITY',
                'related_menu_code' => null,
                'description' => '4 data identitas wajib per akun, aturan SATU nomor HP = nomor WhatsApp (emp_employee.phone), dan syarat layar yang mengirim dokumen pribadi ke user.',
                'sort_order' => 112,
                'activate' => 1,
            ]
        );
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'usersecuritystandard.md')->delete();
    }
};
