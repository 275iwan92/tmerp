<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Layar Master Posisi (HRM_ORG_STRUCTURE, dulu placeholder "Departemen &
// Posisi") + Struktur Organisasi (org chart, item baru) sudah dibangun --
// instruksi user 2026-08-29. Relabel + READY_FOR_QC.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'HRM_ORG_STRUCTURE')->update([
            'label' => 'Master Posisi',
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
        ]);

        if ($sys->table('sys_app_menu')->where('code', 'HRM_ORG_CHART')->exists()) {
            return;
        }

        $orgGroupId = $sys->table('sys_app_menu')->where('code', 'HRM_ORG')->value('id');
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $orgGroupId,
            'code' => 'HRM_ORG_CHART',
            'label' => 'Struktur Organisasi',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target_kind' => 'app',
            'sort_order' => 20,
            'activate' => true,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->where('code', 'HRM_ORG_CHART')->delete();
        $sys->table('sys_app_menu')->where('code', 'HRM_ORG_STRUCTURE')->update(['label' => 'Departemen & Posisi', 'status_code' => 'NOT_BUILT']);
    }
};
