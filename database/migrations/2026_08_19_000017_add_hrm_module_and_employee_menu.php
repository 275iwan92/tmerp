<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Modul HRM baru (Master Employee, menyusul Shift Roster & Self-Service).
// sec_module baru 'HRM' + sys_app_menu module/group/item, pola sama dgn
// migration COSTING (2026_08_15) dan FINACC (2026_08_19_000010). DIREKSI
// di-grant dulu (belum ada grup HR Officer/Admin App khusus, masteremployee_tier1.md §7
// role itu baru simulasi di layar, bukan sec_group nyata).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $hrmModuleId = $sys->table('sec_module')->where('module_code', 'HRM')->value('id_module');

        if (! $hrmModuleId) {
            $hrmModuleId = $sys->table('sec_module')->insertGetId([
                'module_code' => 'HRM',
                'module_name' => 'Human Resources',
                'icon' => 'fa-solid fa-users',
                'route_prefix' => 'hrm',
                'min_mgmt_level' => 2,
                'is_reportable' => true,
                'sort_order' => 45,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $direksiId = $sys->table('sec_group')->where('group_code', 'DIREKSI')->value('id_group');

        if ($direksiId) {
            $sys->table('sec_group_permission')->updateOrInsert(
                ['group_id' => $direksiId, 'module_id' => $hrmModuleId],
                ['can_access' => true, 'can_input' => true, 'can_approve' => true, 'can_export' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        if (! $sys->table('sys_app_menu')->where('code', 'HRM')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => null,
                'code' => 'HRM',
                'label' => 'Human Resources',
                'menu_type' => 'module',
                'object_type' => null,
                'target_kind' => 'app',
                'sort_order' => 45,
                'is_active' => true,
                'created_at' => now(),
                'required_object' => 'HRM',
            ]);
        }

        $hrmMenuId = $sys->table('sys_app_menu')->where('code', 'HRM')->value('id');

        if (! $sys->table('sys_app_menu')->where('code', 'HRM_MASTER')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => $hrmMenuId,
                'code' => 'HRM_MASTER',
                'label' => 'Master Data',
                'menu_type' => 'group',
                'object_type' => null,
                'target_kind' => 'app',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => now(),
            ]);
        }

        $hrmMasterId = $sys->table('sys_app_menu')->where('code', 'HRM_MASTER')->value('id');

        if (! $sys->table('sys_app_menu')->where('code', 'HRM_EMPLOYEE')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => $hrmMasterId,
                'code' => 'HRM_EMPLOYEE',
                'label' => 'Master Employee',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target_kind' => 'app',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->whereIn('code', ['HRM_EMPLOYEE', 'HRM_MASTER'])->delete();

        $hrmModuleId = $sys->table('sec_module')->where('module_code', 'HRM')->value('id_module');

        if ($hrmModuleId) {
            $sys->table('sys_app_menu')->where('code', 'HRM')->delete();
            $sys->table('sec_group_permission')->where('module_id', $hrmModuleId)->delete();
            $sys->table('sec_module')->where('id_module', $hrmModuleId)->delete();
        }
    }
};
