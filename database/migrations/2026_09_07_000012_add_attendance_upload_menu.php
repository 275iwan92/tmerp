<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Modul 01 (Upload Finger/Excel) -- ditaruh SEBELUM "Presensi" (sort=20)
// di grup Time Attendance (924), krn urutan proses: upload dulu baru
// review grid (permintaan user 2026-09-07 soal urutan menu ikut flow).
return new class extends Migration
{
    public function up(): void
    {
        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        DB::connection('tm2sys')->table('sys_app_menu')->updateOrInsert(
            ['code' => 'HRM_ATTENDANCE_UPLOAD'],
            [
                'id' => $nextId,
                'parent_id' => 924,
                'label' => 'Upload Presensi',
                'menu_type' => 'item',
                'object_type' => 'form',
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
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_ATTENDANCE_UPLOAD')->delete();
    }
};
