<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/BACKLOG.md §3 "Win-rate Sales" -- report baru di grup Laporan Sales
// (parent_id=180=SALES_MON, sudah diseragamkan label "Laporan" 2026-09-02
// pagi via migration 2026_09_02_000017).
return new class extends Migration
{
    public function up(): void
    {
        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        DB::connection('tm2sys')->table('sys_app_menu')->updateOrInsert(
            ['code' => 'SALES_WINRATE'],
            [
                'id' => $nextId,
                'parent_id' => 180,
                'label' => 'Win-Rate Sales',
                'menu_type' => 'item',
                'object_type' => 'report',
                'target_kind' => 'app',
                'sort_order' => 15,
                'activate' => 1,
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SALES_WINRATE')->delete();
    }
};
