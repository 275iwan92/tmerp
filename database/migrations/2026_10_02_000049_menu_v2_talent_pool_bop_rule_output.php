<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-20 rollout (backlog01): Talent Pool dan BOP Rule Setting -> grid v2 (buka / edit = layar v1). Production Output memakai
// layar yang sama dengan Work Order (di v1 keduanya juga satu layar: production.wo.list) -- menu = data, target sama.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const MAP = ['HRM_RCR_TALENTPOOL' => 'rcr-talent-pool', 'PROD_MST_BOP_RULE' => 'prod-bop-rule', 'PROD_OUTPUT' => 'prod-work-order'];

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
