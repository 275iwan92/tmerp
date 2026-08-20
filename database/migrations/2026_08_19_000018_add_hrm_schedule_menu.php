<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Grup menu "Penjadwalan" di bawah modul HRM (id dari migration
// 2026_08_19_000017) + leaf "Shift Roster" -> pages::hrm.shift-roster.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $hrmMenuId = $sys->table('sys_app_menu')->where('code', 'HRM')->value('id');

        if (! $hrmMenuId) {
            return;
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        if (! $sys->table('sys_app_menu')->where('code', 'HRM_SCHEDULE')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => $hrmMenuId,
                'code' => 'HRM_SCHEDULE',
                'label' => 'Penjadwalan',
                'menu_type' => 'group',
                'object_type' => null,
                'target_kind' => 'app',
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => now(),
            ]);
        }

        $scheduleId = $sys->table('sys_app_menu')->where('code', 'HRM_SCHEDULE')->value('id');

        if (! $sys->table('sys_app_menu')->where('code', 'HRM_ROSTER')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => $scheduleId,
                'code' => 'HRM_ROSTER',
                'label' => 'Shift Roster',
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
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['HRM_ROSTER', 'HRM_SCHEDULE'])->delete();
    }
};
