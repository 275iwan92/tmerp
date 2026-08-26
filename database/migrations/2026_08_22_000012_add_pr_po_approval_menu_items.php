<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PR/PO Approval Center sudah live (route pur.pr.approval / pur.po.approval)
// tapi belum pernah masuk sys_app_menu -- satu-satunya jalan masuk selama ini
// adalah link navy header di grid pr/index & po/index, yang justru melanggar
// NAV-SHELL-CONTRACT (grid tak boleh punya chrome sendiri, docs/griduistandard.md
// #1). Tambahkan sebagai item resmi di grup PROC_TRX (id 210) supaya header itu
// bisa dihapus dari grid tanpa menghilangkan akses ke layar approval.
return new class extends Migration
{
    public function up(): void
    {
        $maxId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id');

        DB::connection('tm2sys')->table('sys_app_menu')->insert([
            [
                'id' => $maxId + 1,
                'parent_id' => 210,
                'code' => 'PROC_PR_APPR',
                'label' => 'PR Approval Center',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target' => '',
                'target_kind' => 'app',
                'sort_order' => 15,
                'is_active' => 1,
                'status_code' => 'READY_FOR_QC',
                'created_at' => now(),
            ],
            [
                'id' => $maxId + 2,
                'parent_id' => 210,
                'code' => 'PROC_PO_APPR',
                'label' => 'PO Approval Center',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target' => '',
                'target_kind' => 'app',
                'sort_order' => 35,
                'is_active' => 1,
                'status_code' => 'READY_FOR_QC',
                'created_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['PROC_PR_APPR', 'PROC_PO_APPR'])->delete();
    }
};
