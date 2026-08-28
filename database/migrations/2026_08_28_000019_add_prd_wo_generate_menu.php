<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Screen #1 (Generate WO dari Plan Delivery) -- docs/production_scheduling_wo_generation_delta.md.
// Sibling PROD_WO_LIST di grup PROD_PLAN, sort_order 14 (sebelum 15 = PROD_WO_LIST).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $planId = $sys->table('sys_app_menu')->where('code', 'PROD_PLAN')->value('id');
        if (! $planId || $sys->table('sys_app_menu')->where('code', 'PROD_WO_GENERATE')->exists()) {
            return;
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $planId,
            'code' => 'PROD_WO_GENERATE',
            'label' => 'Generate WO dari Plan Delivery',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target_kind' => 'app',
            'sort_order' => 14,
            'activate' => true,
            'status_code' => 'NOT_BUILT',
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_WO_GENERATE')->delete();
    }
};
