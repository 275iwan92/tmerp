<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Worklist WO, Rule Library, MTS Forecast Entry (2026_08_28_000007) sudah
// diverifikasi jalan (tests/Feature/ProductionWorklistWoTest.php, 8/8 lulus,
// termasuk role-gate QC/Ops Manager) -- promosikan dari NOT_BUILT ke
// READY_FOR_QC, pola sama seed_prd_scheduling_board_dummy.php.
return new class extends Migration
{
    private array $codes = ['PROD_WO_LIST', 'PROD_RULE_LIB', 'PROD_MTS_FORECAST'];

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', $this->codes)
            ->update([
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', $this->codes)
            ->update(['status_code' => 'NOT_BUILT', 'status_updated_by' => null, 'status_updated_at' => null]);
    }
};
