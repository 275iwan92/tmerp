<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu "BOP Rule Setting" (Production > Master) -- layar pengaturan admin
// untuk engine Bill of Process/Material ProDev (prod_bop_rule).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');

        if ($db->table('sys_app_menu')->where('code', 'PROD_MST_BOP_RULE')->exists()) {
            return;
        }

        $db->table('sys_app_menu')->insert([
            'id' => (int) $db->table('sys_app_menu')->max('id') + 1,
            'parent_id' => 410, // PROD_MASTER
            'code' => 'PROD_MST_BOP_RULE',
            'label' => 'BOP Rule Setting',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target_kind' => 'app',
            'sort_order' => 35,
            'activate' => 1,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_MST_BOP_RULE')->delete();
    }
};
