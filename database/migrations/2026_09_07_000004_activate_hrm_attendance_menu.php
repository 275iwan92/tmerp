<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Layar Presensi Harian (route hrm.attendance-daily) sudah dibangun --
// menu "Presensi" (HRM_ATTENDANCE) sudah ada sbg placeholder NOT_BUILT
// sejak 2026-08-24, tinggal diaktifkan (route ditautkan di code=>route map
// main-menu.blade.php, bukan kolom `target`, sama pola dgn HRM_LEAVE_APPROVAL).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'HRM_ATTENDANCE')
            ->update([
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'HRM_ATTENDANCE')
            ->update(['status_code' => 'NOT_BUILT', 'status_updated_by' => null, 'status_updated_at' => null]);
    }
};
