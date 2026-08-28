<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Kelola Tipe Scrap -- data pendukung F15 (prd_scrap_type), grup Output
// (sibling PROD_OUTPUT/PROD_MON_WO), sudah diverifikasi jalan
// (tests/Feature/ProductionWorklistWoTest.php). Pola sama migration
// 2026_08_27_000015_add_hrm_correction_requests_menu.php.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $outId = $sys->table('sys_app_menu')->where('code', 'PROD_OUT')->value('id');
        if (! $outId || $sys->table('sys_app_menu')->where('code', 'PROD_SCRAP_TYPE')->exists()) {
            return;
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $outId,
            'code' => 'PROD_SCRAP_TYPE',
            'label' => 'Kelola Tipe Scrap',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target_kind' => 'app',
            'sort_order' => 30,
            'activate' => true,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_SCRAP_TYPE')->delete();
    }
};
