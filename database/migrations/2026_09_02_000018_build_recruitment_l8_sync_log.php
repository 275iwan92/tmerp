<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// L8 Recruitment (Rekap Sync Log, docs/recruitment_tier4.md) sengaja
// dilewati saat modul dibangun 2026-08-29 -- menu placeholder HRM_RCR_SYNCLOG
// sudah ada (status NOT_BUILT) tapi layarnya belum. docs/BACKLOG.md §8 item
// "mudah" #6: data rcr_sync_log memang sudah ada (PortalSyncService nulis ke
// sini tiap sync), jadi cukup dibuatkan grid read-only, bukan bangun dari nol.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'HRM_RCR_SYNCLOG')
            ->update([
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'HRM_RCR_SYNCLOG')
            ->update(['status_code' => 'NOT_BUILT']);
    }
};
