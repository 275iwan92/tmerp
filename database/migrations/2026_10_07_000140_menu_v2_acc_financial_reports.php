<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C2: Financial Reports (Financial Statements, Balance Sheet, Income Statement, Cash Flow) pindah ke layar v2 (FinancialStatements + x-dl::statement).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const MAP = ['ACC_FS' => 'v2:acc-statements', 'ACC_BS' => 'v2:acc-rpt-balance-sheet', 'ACC_PL' => 'v2:acc-rpt-income-statement', 'ACC_CF' => 'v2:acc-rpt-cash-flow'];

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
