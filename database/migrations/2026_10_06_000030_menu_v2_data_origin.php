<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Setup > Data Origin pindah ke layar v2 (user 2026-10-06: layar v1 dibersihkan; gelombang 1 rencana pengganti v1).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_DATA_ORIGIN')->update(['target' => 'v2:data-origin', 'target_kind' => 'app',
            'status_code' => 'READY_FOR_QC', 'status_note' => 'v2 Data Origin (App\Support\V2\Data\DataOrigin + DataOriginService)',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_DATA_ORIGIN')->update(['target' => null, 'status_code' => 'NOT_BUILT']);
    }
};
