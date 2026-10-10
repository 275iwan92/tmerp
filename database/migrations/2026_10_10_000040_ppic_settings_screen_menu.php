<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// "All PPIC Settings" (eight categories with search) is the first item of the PPIC Settings group. Dummy menu database first (--env=mockup).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $group = $sys->table('sys_app_menu')->where('code', 'PROD_PPIC_SET')->value('id');
        if ($group && ! $sys->table('sys_app_menu')->where('code', 'PROD_PPIC_ALL')->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => $group, 'code' => 'PROD_PPIC_ALL', 'label' => 'All PPIC Settings', 'icon' => 'fa-solid fa-sliders', 'menu_type' => 'item', 'object_type' => 'form',
                'target' => 'v2:ppic-settings', 'sort_order' => 1, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_PPIC_ALL')->delete();
    }
};
