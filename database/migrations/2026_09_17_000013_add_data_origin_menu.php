<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu Settings > "Dummy & Legacy Data" (BACKLOG §18.7), khusus admin.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');

        if ($db->table('sys_app_menu')->where('code', 'SET_DATA_ORIGIN')->exists()) {
            return;
        }

        $db->table('sys_app_menu')->insert([
            'id' => (int) $db->table('sys_app_menu')->max('id') + 1,
            'parent_id' => $db->table('sys_app_menu')->where('code', 'SET_GRP_SETTINGS')->value('id'),
            'code' => 'SET_DATA_ORIGIN',
            'label' => 'Dummy & Legacy Data',
            'menu_type' => 'item',
            'object_type' => 'form',
            'target_kind' => 'app',
            'sort_order' => 45,
            'activate' => 1,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_DATA_ORIGIN')->delete();
    }
};
