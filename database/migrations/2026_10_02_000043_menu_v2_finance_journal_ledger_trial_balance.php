<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-20 rollout (backlog01): Finance -> grid v2. Journal Entries = Grid Transactional (buka = layar v1),
// General Ledger dan Trial Balance = Grid Report.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // kode => [slug v2, jenis objek]
    private const MAP = [
        'ACC_JRN' => ['acc-journal', 'grid'],
        'ACC_GL' => ['acc-rpt-ledger', 'report'],
        'ACC_TB' => ['acc-rpt-trial-balance', 'report'],
    ];

    public function up(): void
    {
        foreach (self::MAP as $code => [$slug, $type]) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)
                ->where(fn ($q) => $q->whereNull('target')->orWhere('target', 'not like', 'v2:%'))
                ->update(['target' => 'v2:'.$slug, 'object_type' => $type, 'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $code => [$slug]) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->where('target', 'v2:'.$slug)->update(['target' => null]);
        }
    }
};
