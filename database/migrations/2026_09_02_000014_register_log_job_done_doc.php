<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan LOG_JOB_DONE.md (docs root) -- dipisah dari BACKLOG.md 2026-09-02
// (permintaan user: "pindahkan ke file LOG Job Done md file... backlog md
// mengecil"). CLAUDE.md §6: sys_doc_reference sumber kebenarannya migration.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_doc_reference')->where('file_name', 'LOG_JOB_DONE.md')->exists()) {
            return;
        }

        $sys->table('sys_doc_reference')->insert([
            'file_name' => 'LOG_JOB_DONE.md',
            'title' => 'Log Riwayat Pekerjaan Selesai',
            'category' => 'TRACKING',
            'related_menu_code' => null,
            'description' => 'Arsip item BACKLOG.md yang sudah selesai dikerjakan, dipisah supaya backlog tetap ramping. Urutan terbaru di atas.',
            'sort_order' => 6,
            'activate' => true,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'LOG_JOB_DONE.md')->delete();
    }
};
