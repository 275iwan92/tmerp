<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C7: Payroll > Bank Transfer Generator dan Cash Roster & Denominations pindah ke layar v2 (v2:hrm-bank-transfer, v2:hrm-cash-roster).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const MAP = ['HRM_PAYROLL_BANKXFER' => 'v2:hrm-bank-transfer', 'HRM_PAYROLL_CASHROSTER' => 'v2:hrm-cash-roster'];

    public function up(): void
    {
        foreach (self::MAP as $code => $target) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->update(['target' => $target, 'object_type' => 'report',
                'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', array_keys(self::MAP))->update(['target' => null]);
    }
};
