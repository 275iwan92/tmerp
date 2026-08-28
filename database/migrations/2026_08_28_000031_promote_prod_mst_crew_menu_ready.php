<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Master Crew (grid+form production.master-crew, tabel baru prod_crew, data
// riil dimigrasi dari tm2old.mst_machine) selesai dibangun -- promosikan dari
// NOT_BUILT ke READY_FOR_QC, pola sama promote_prod_mst_process_menu_ready.php.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'PROD_MST_CREW')
            ->update([
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'PROD_MST_CREW')
            ->update(['status_code' => 'NOT_BUILT', 'status_updated_by' => null, 'status_updated_at' => null]);
    }
};
