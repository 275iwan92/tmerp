<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Modul 05 Dispatch -- daftar sec_module + sys_app_menu (dispatch_tier4.md §9
// checklist "Menu sidebar Dispatch ditambahkan"). Pola sama persis migration
// consolidate_setup_security_and_add_costing_module.php (COSTING) yg nambah
// module BARU sebelumnya di project ini.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $dispatchModuleId = $sys->table('sec_module')->where('module_code', 'DISPATCH')->value('id_module');

        if (! $dispatchModuleId) {
            $dispatchModuleId = $sys->table('sec_module')->insertGetId([
                'module_code' => 'DISPATCH',
                'module_name' => 'Dispatch',
                'icon' => 'fa-solid fa-truck-fast',
                'route_prefix' => 'dispatch',
                'min_mgmt_level' => 1,
                'is_reportable' => true,
                'sort_order' => 65,
                'activate' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Aktor Tier1 §4: Admin Sales/Dispatch (buat DO), Finance/Sales (monitor
        // nilai). Tidak ada grup "Gudang/Ekspedisi" tersendiri di sec_group saat
        // ini -- OPS_MGR paling dekat (operasional). DIREKSI selalu penuh.
        foreach (['SALES_STAFF', 'SALES_SPV', 'OPS_MGR', 'DIREKSI'] as $groupCode) {
            $groupId = $sys->table('sec_group')->where('group_code', $groupCode)->value('id_group');

            if ($groupId) {
                $sys->table('sec_group_permission')->updateOrInsert(
                    ['group_id' => $groupId, 'module_id' => $dispatchModuleId],
                    ['can_access' => true, 'can_input' => true, 'can_approve' => $groupCode !== 'SALES_STAFF', 'can_export' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $moduleMenuId = $sys->table('sys_app_menu')->where('code', 'DISPATCH')->value('id');

        if (! $moduleMenuId) {
            $moduleMenuId = $nextId++;
            $sys->table('sys_app_menu')->insert([
                'id' => $moduleMenuId,
                'parent_id' => null,
                'code' => 'DISPATCH',
                'label' => 'Dispatch',
                'menu_type' => 'module',
                'required_object' => 'DISPATCH',
                'sort_order' => 65,
                'activate' => true,
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        if (! $sys->table('sys_app_menu')->where('code', 'DISPATCH_DO')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => $moduleMenuId,
                'code' => 'DISPATCH_DO',
                'label' => 'Delivery Order',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'sort_order' => 10,
                'activate' => true,
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'DISPATCH_DO')->delete();
        $sys->table('sys_app_menu')->where('code', 'DISPATCH')->delete();

        $dispatchModuleId = $sys->table('sec_module')->where('module_code', 'DISPATCH')->value('id_module');

        if ($dispatchModuleId) {
            $sys->table('sec_group_permission')->where('module_id', $dispatchModuleId)->delete();
            $sys->table('sec_module')->where('id_module', $dispatchModuleId)->delete();
        }
    }
};
