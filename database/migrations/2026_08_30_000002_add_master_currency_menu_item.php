<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Master Currency -- layar BARU (fin_currency sebelumnya cuma dipakai lewat
// dropdown di layar lain, tak pernah punya CRUD sendiri). Pilot #1
// docs/spreadsheetgridstandard.md (adopsi perilaku AutoFill DataTables.net
// versi ringan, keputusan user 2026-08-30).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_app_menu')->where('code', 'MD_CURRENCY')->exists()) {
            return;
        }

        $parentId = $sys->table('sys_app_menu')->where('code', 'MDATA')->value('id');

        if (! $parentId) {
            return;
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $parentId,
            'code' => 'MD_CURRENCY',
            'label' => 'Master Currency',
            'menu_type' => 'item',
            'object_type' => 'form',
            'target_kind' => 'app',
            'sort_order' => 90,
            'activate' => true,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MD_CURRENCY')->delete();
    }
};
