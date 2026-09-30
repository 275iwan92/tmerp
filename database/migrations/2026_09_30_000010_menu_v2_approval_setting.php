<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu v2 (user 2026-09-30): Approval Center Stock Adjustment/Opname (RM/FG/SFG) & Purchase Return; Setup > Settings:
// Setting Approval + Officer Pengganti (PLT).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $now = now();
        $ready = ['target_kind' => 'app', 'status_code' => 'READY_FOR_QC', 'status_note' => 'v2 Approval (DevLib Form Approval / Setting Approval)',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => $now];
        $db->table('sys_app_menu')->whereIn('code', ['INV_RM_ITEM5', 'INV_FG_ITEM5', 'INV_SFG_ITEM5'])->update(['target' => 'v2:approval-stock'] + $ready);
        $db->table('sys_app_menu')->where('code', 'PROC_RET_APPR')->update(['label' => 'Purchase Return Approval Center', 'target' => 'v2:approval-prt'] + $ready);

        $group = $db->table('sys_app_menu')->where('code', 'SET_GRP_SETTINGS')->value('id');
        foreach ([['SET_APPROVAL', 'Setting Approval', 'approval-setting', 42], ['SET_APPROVAL_PLT', 'Officer Pengganti (PLT)', 'approval-acting', 43]] as [$code, $label, $slug, $sort]) {
            if ($group && ! $db->table('sys_app_menu')->where('code', $code)->exists()) {
                $db->table('sys_app_menu')->insert(['parent_id' => $group, 'code' => $code, 'label' => $label, 'menu_type' => 'item', 'object_type' => 'form',
                    'target' => 'v2:'.$slug, 'sort_order' => $sort, 'activate' => 1, 'created_at' => $now] + $ready);
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');
        $db->table('sys_app_menu')->whereIn('code', ['SET_APPROVAL', 'SET_APPROVAL_PLT'])->delete();
        $db->table('sys_app_menu')->whereIn('code', ['INV_RM_ITEM5', 'INV_FG_ITEM5', 'INV_SFG_ITEM5', 'PROC_RET_APPR'])->update(['target' => null, 'status_code' => 'NOT_BUILT']);
    }
};
