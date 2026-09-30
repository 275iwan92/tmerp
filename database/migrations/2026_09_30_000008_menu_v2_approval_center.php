<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu v2 Approval Center (DevLib Form Approval, user 2026-09-30): PR/PO yang sudah ada diarahkan ke layar v2,
// CO Approval Center baru di grup Sales Order (sesudah Customer Plan Delivery).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $now = now();
        $ready = ['target_kind' => 'app', 'status_code' => 'READY_FOR_QC', 'status_note' => 'v2 Approval Center (DevLib Form Approval)',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => $now];
        $db->table('sys_app_menu')->where('code', 'PROC_PR_APPR')->update(['target' => 'v2:approval-pr'] + $ready);
        $db->table('sys_app_menu')->where('code', 'PROC_PO_APPR')->update(['target' => 'v2:approval-po'] + $ready);

        $cpdel = $db->table('sys_app_menu')->where('code', 'SALES_CPDEL')->first();
        if ($cpdel && ! $db->table('sys_app_menu')->where('code', 'SALES_CO_APPR')->exists()) {
            $db->table('sys_app_menu')->insert(['parent_id' => $cpdel->parent_id, 'code' => 'SALES_CO_APPR', 'label' => 'CO Approval Center', 'menu_type' => 'item',
                'object_type' => 'grid', 'target' => 'v2:approval-co', 'sort_order' => $cpdel->sort_order + 5, 'activate' => 1, 'created_at' => $now] + $ready);
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');
        $db->table('sys_app_menu')->where('code', 'SALES_CO_APPR')->delete();
        $db->table('sys_app_menu')->whereIn('code', ['PROC_PR_APPR', 'PROC_PO_APPR'])->update(['target' => '', 'status_code' => 'NOT_BUILT']);
    }
};
