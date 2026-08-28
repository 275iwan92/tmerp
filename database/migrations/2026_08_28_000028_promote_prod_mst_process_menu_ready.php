<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Master Proses (grid+form production.master-process, data riil dimigrasi
// dari tm2old.m_process) selesai dibangun -- promosikan dari NOT_BUILT ke
// READY_FOR_QC, pola sama promote_prd_worklist_menu_ready.php.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'PROD_MST_PROCESS')
            ->update([
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'PROD_MST_PROCESS')
            ->update(['status_code' => 'NOT_BUILT', 'status_updated_by' => null, 'status_updated_at' => null]);
    }
};
