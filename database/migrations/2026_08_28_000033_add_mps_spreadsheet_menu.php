<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-08-28: layar baru "MPS - Spreadsheet" -- UI/UX
// kompatibel Google Sheets (dblclick edit Est Mulai/Selesai, dropdown
// Mesin/Operator) di atas data prd_wo_operation yang SAMA dgn WO Detail/
// Gantt/Kanban, lewat PdrEngine yang sama juga. Ditaruh sbg baris ke-4
// berurutan di grup Planning, sesudah "MPS - Kanban Board".
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $planId = $sys->table('sys_app_menu')->where('code', 'PROD_PLAN')->value('id');
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $planId,
            'code' => 'PROD_MPS_SHEET',
            'label' => 'MPS - Spreadsheet',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target' => 'grid_mps_spreadsheet.php',
            'target_kind' => 'app',
            'sort_order' => 13,
            'activate' => true,
            'created_at' => now(),
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_MPS_SHEET')->delete();
    }
};
