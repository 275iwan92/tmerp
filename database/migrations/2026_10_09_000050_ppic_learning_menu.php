<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Learning Changes (what the learning engine adjusted, with revert) joins Production > Planning. Dummy menu database first (--env=mockup); live at release.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $parent = $sys->table('sys_app_menu')->where('code', 'PROD_PLAN')->first();
        if (! $parent || $sys->table('sys_app_menu')->where('code', 'PROD_LEARNING')->exists()) {
            return;
        }
        $sys->table('sys_app_menu')->insert([
            'parent_id' => $parent->id, 'code' => 'PROD_LEARNING', 'label' => 'Learning Changes', 'icon' => 'fa-solid fa-graduation-cap', 'menu_type' => 'item', 'object_type' => 'form',
            'target' => 'v2:production-learning', 'sort_order' => 21, 'activate' => 1, 'required_object' => $parent->required_object, 'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_LEARNING')->delete();
    }
};
