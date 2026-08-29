<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Tandai L1-L9 (minus L8, dilewati) READY_FOR_QC sekarang layarnya sudah
// dibangun + route terwire (lihat main-menu.blade.php itemRoutes()). Tambah
// 1 item baru "Portal Candidate" -- simulasi portal kandidat (P1-P4 gabung 1
// layar), ditaruh SEMENTARA di grup Recruitment ini sesuai instruksi user
// 2026-08-29 (lihat docs/recruitment_state.yaml simulasi_portal_sementara).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->whereIn('code', [
            'HRM_RCR_REQUISITION', 'HRM_RCR_VACANCY', 'HRM_RCR_TALENTPOOL',
            'HRM_RCR_PIPELINE', 'HRM_RCR_INTERVIEW', 'HRM_RCR_ASSESSMENT',
            'HRM_RCR_OFFER', 'HRM_RCR_PROVISION',
        ])->update([
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
        ]);

        if ($sys->table('sys_app_menu')->where('code', 'HRM_RCR_PORTAL')->exists()) {
            return;
        }

        $recruitmentGroupId = $sys->table('sys_app_menu')->where('code', 'HRM_RECRUITMENT')->value('id');
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $recruitmentGroupId,
            'code' => 'HRM_RCR_PORTAL',
            'label' => 'Portal Candidate',
            'menu_type' => 'item',
            'object_type' => 'form',
            'target_kind' => 'app',
            'sort_order' => 100,
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

        $sys->table('sys_app_menu')->where('code', 'HRM_RCR_PORTAL')->delete();

        $sys->table('sys_app_menu')->whereIn('code', [
            'HRM_RCR_REQUISITION', 'HRM_RCR_VACANCY', 'HRM_RCR_TALENTPOOL',
            'HRM_RCR_PIPELINE', 'HRM_RCR_INTERVIEW', 'HRM_RCR_ASSESSMENT',
            'HRM_RCR_OFFER', 'HRM_RCR_PROVISION',
        ])->update(['status_code' => 'NOT_BUILT']);
    }
};
