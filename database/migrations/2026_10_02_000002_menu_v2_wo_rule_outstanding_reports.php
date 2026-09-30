<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-20 rollout DbGrid (backlog01): Production Work Order + Rule Library, Procurement Outstanding PR/PO/PV -> grid DB v2.
// down() = target kosong lagi (MenuRoutes v1).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const MAP = [
        'PROD_WO_LIST' => 'prod-work-order', 'PROD_RULE_LIB' => 'prod-rule-library',
        'PROC_MON_PR' => 'pur-rpt-outstanding-pr', 'PROC_MON_PO' => 'pur-rpt-outstanding-po', 'PROC_MON_PV' => 'pur-rpt-outstanding-pv',
    ];

    public function up(): void
    {
        foreach (self::MAP as $code => $slug) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->where(fn ($q) => $q->whereNull('target')->orWhere('target', ''))
                ->update(['target' => 'v2:'.$slug, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $code => $slug) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->where('target', 'v2:'.$slug)
                ->update(['target' => null, 'status_code' => 'NOT_BUILT']);
        }
    }
};
