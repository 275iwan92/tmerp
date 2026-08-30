<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Pasangan tampilan MPS Gantt (PROD_MPS, sudah ada) -- Kanban board per
// task_status, sumber data SAMA (prd_wo_operation) jadi otomatis konsisten
// dengan Gantt begitu salah satu diubah (baca live + wire:poll, tanpa
// broadcasting -- app ini belum punya infra realtime). Pola insert sama
// 2026_08_28_000007_add_prd_worklist_rule_forecast_menu.php.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $planId = $sys->table('sys_app_menu')->where('code', 'PROD_PLAN')->value('id');
        if (! $planId) {
            return;
        }

        if ($sys->table('sys_app_menu')->where('code', 'PROD_MPS_KANBAN')->exists()) {
            return;
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $planId,
            'code' => 'PROD_MPS_KANBAN',
            'label' => 'MPS Kanban Board',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target_kind' => 'app',
            'sort_order' => 11,
            'activate' => true,
            'status_code' => 'READY_FOR_QC',
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_MPS_KANBAN')->delete();
    }
};
