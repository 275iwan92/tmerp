<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// module_status_system (globalrules.yaml): Customer Order Entry + List selesai
// dibangun & diuji end-to-end (2026-08-20) -- tandai SALES_SO siap-QC, sama pola
// dgn migration 2026_08_20_000006.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'SALES_SO')
            ->update([
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'SALES_SO')
            ->update([
                'status_code' => 'NOT_BUILT',
                'status_note' => null,
                'status_updated_by' => null,
                'status_updated_at' => null,
            ]);
    }
};
