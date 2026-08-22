<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PQ (Purchase Quotation) & PRT (Purchase Return) dibangun 2026-08-22 dari
// mockup_purchase_quotation.html / mockup_purchase_return.html. Placeholder
// menu PROC_PQ (id 212) & PROC_RET (id 215) sudah ada sejak draf awal, tinggal
// dipindah NOT_BUILT -> READY_FOR_QC. PRT butuh approval berjenjang sungguhan
// (beda dari PQ yang cuma self-confirm) -- tambah 1 menu baru utk Approval
// Center-nya, sama pola PROC_PR_APPR/PROC_PO_APPR (migration 000012).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')->where('code', 'PROC_PQ')->update(['status_code' => 'READY_FOR_QC']);
        $db->table('sys_app_menu')->where('code', 'PROC_RET')->update(['status_code' => 'READY_FOR_QC']);

        $maxId = (int) $db->table('sys_app_menu')->max('id');

        if (! $db->table('sys_app_menu')->where('code', 'PROC_RET_APPR')->exists()) {
            $db->table('sys_app_menu')->insert([
                'id' => $maxId + 1,
                'parent_id' => 210, // PROC_TRX
                'code' => 'PROC_RET_APPR',
                'label' => 'Persetujuan Purchase Return',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target' => '',
                'target_kind' => 'app',
                'sort_order' => 55,
                'is_active' => 1,
                'status_code' => 'READY_FOR_QC',
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')->where('code', 'PROC_PQ')->update(['status_code' => 'NOT_BUILT']);
        $db->table('sys_app_menu')->where('code', 'PROC_RET')->update(['status_code' => 'NOT_BUILT']);
        $db->table('sys_app_menu')->where('code', 'PROC_RET_APPR')->delete();
    }
};
