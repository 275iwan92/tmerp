<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-20 rollout (backlog01): Purchase Return dan Support Tickets -> Grid Transactional v2 (buka = form v1).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // kode => [slug v2, target lama]
    private const MAP = [
        'PROC_RET' => ['purchase-return', 'form_purchase_return.php'],
        'SET_TICKETS' => ['support-tickets', null],
    ];

    public function up(): void
    {
        foreach (self::MAP as $code => [$slug]) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)
                ->where(fn ($q) => $q->whereNull('target')->orWhere('target', 'not like', 'v2:%'))
                ->update(['target' => 'v2:'.$slug, 'object_type' => 'grid', 'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $code => [$slug, $old]) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->where('target', 'v2:'.$slug)
                ->update(['target' => $old, 'status_code' => 'NOT_BUILT']);
        }
    }
};
