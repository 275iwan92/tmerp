<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Grid admin "Support Tickets" -- instruksi user 2026-08-29 (fitur Help &
// Support, dropdown profil). Label Inggris (item BARU, lihat CLAUDE.md soal
// cakupan kebijakan bahasa -- bukan rename item SETUP lain yang sudah ada).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_app_menu')->where('code', 'SET_TICKETS')->exists()) {
            return;
        }

        $setupId = $sys->table('sys_app_menu')->where('code', 'SETUP')->value('id');
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $setupId,
            'code' => 'SET_TICKETS',
            'label' => 'Support Tickets',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target_kind' => 'app',
            'sort_order' => 70,
            'activate' => true,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_TICKETS')->delete();
    }
};
