<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-20 rollout DbGrid (backlog01): menu Recruitment -> grid DB v2 (App\Support\V2\Grid\Rcr\*). Pipeline Board & Portal
// Candidate tetap layar v1 (papan kanban / portal publik). down() = target kosong lagi (MenuRoutes v1).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const MAP = [
        'HRM_RCR_REQUISITION' => 'hrm-rcr-requisition', 'HRM_RCR_VACANCY' => 'hrm-rcr-vacancy', 'HRM_RCR_REVIEW' => 'hrm-rcr-review',
        'HRM_RCR_INTERVIEW' => 'hrm-rcr-interview', 'HRM_RCR_ASSESSMENT' => 'hrm-rcr-assessment', 'HRM_RCR_OFFER' => 'hrm-rcr-offer',
        'HRM_RCR_PROVISION' => 'hrm-rcr-provision', 'HRM_RCR_SYNCLOG' => 'hrm-rcr-sync-log',
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
