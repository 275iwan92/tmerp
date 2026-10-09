<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu Warehouse > Task Board (docs/mockups/menu_v201_split.jpg, layar v2:wh-task-board): papan baca-saja pekerjaan gudang gabungan. Grup dan item
// di modul WH hasil pemisahan menu (2026_10_09_000400); urutan antara Tools (70) dan Setup (90). Idempoten. Jalankan hanya dengan --env=mockup sampai rilis ke tm2*.
return new class extends Migration
{
    private const BY = 'claude (task board v2.01)';

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $wh = $menu()->where('code', 'WH')->where('menu_type', 'module')->value('id');
        if (! $wh) {
            return;
        }
        $row = fn (array $v) => $v + ['activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now(), 'created_at' => now(),
            'target_kind' => 'app', 'doc_slug' => 'warehouselayout'];
        $group = $menu()->where('code', 'WH_TASK')->value('id')
            ?? $menu()->insertGetId($row(['parent_id' => $wh, 'code' => 'WH_TASK', 'label' => 'Task Board', 'icon' => 'fa-solid fa-table-columns', 'menu_type' => 'group', 'sort_order' => 80]));
        if (! $menu()->where('code', 'WH_TASK_BOARD')->exists()) {
            $menu()->insert($row(['parent_id' => $group, 'code' => 'WH_TASK_BOARD', 'label' => 'Task Board', 'icon' => 'fa-solid fa-table-columns', 'menu_type' => 'item', 'object_type' => 'form',
                'target' => 'v2:wh-task-board', 'sort_order' => 10]));
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['WH_TASK_BOARD', 'WH_TASK'])->delete();
    }
};
