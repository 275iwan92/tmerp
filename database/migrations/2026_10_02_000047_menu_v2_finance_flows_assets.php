<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-20 rollout (backlog01): Finance -> Grid Transactional v2 (buka / New = layar v1): Cash & Bank, Down Payment,
// Order to Cash, Procure to Pay, Fixed Assets, Amortization.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const MAP = [
        'FIN_CASH' => 'acc-cash-bank', 'FIN_DP' => 'acc-dp-invoice', 'FIN_AR' => 'acc-receivables', 'FIN_AP' => 'acc-payables',
        'ACC_FIXEDASSET' => 'acc-fixed-asset', 'ACC_AMORT' => 'acc-amortization',
    ];

    public function up(): void
    {
        foreach (self::MAP as $code => $slug) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)
                ->where(fn ($q) => $q->whereNull('target')->orWhere('target', 'not like', 'v2:%'))
                ->update(['target' => 'v2:'.$slug, 'object_type' => 'grid', 'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $code => $slug) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->where('target', 'v2:'.$slug)->update(['target' => null]);
        }
    }
};
