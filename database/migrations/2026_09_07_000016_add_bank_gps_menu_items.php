<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Modul 07 (Master Karyawan+Rekening, grup Payroll) + Modul 09 (GPS
// Clock-in, grup Time Attendance -- link cepat utk QC/testing, pemakaian
// riil pegawai via bookmark HP bukan navigasi sidebar desktop) + Approval
// Lokasi GPS (pelengkap 09, grup Time Attendance dekat Approval Cuti).
return new class extends Migration
{
    public function up(): void
    {
        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        $items = [
            ['code' => 'HRM_PAYROLL_BANK', 'parent_id' => 974, 'label' => 'Master Karyawan & Rekening Bank', 'sort_order' => 7],
            ['code' => 'HRM_CLOCKIN', 'parent_id' => 924, 'label' => 'Absen GPS (Mobile)', 'sort_order' => 12],
            ['code' => 'HRM_GPS_APPROVAL', 'parent_id' => 924, 'label' => 'Approval Lokasi GPS', 'sort_order' => 32],
        ];

        foreach ($items as $item) {
            DB::connection('tm2sys')->table('sys_app_menu')->updateOrInsert(
                ['code' => $item['code']],
                [
                    'id' => $nextId++,
                    'parent_id' => $item['parent_id'],
                    'label' => $item['label'],
                    'menu_type' => 'item',
                    'object_type' => 'grid',
                    'target_kind' => 'app',
                    'sort_order' => $item['sort_order'],
                    'activate' => 1,
                    'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => 'claude_code',
                    'status_updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', [
            'HRM_PAYROLL_BANK', 'HRM_CLOCKIN', 'HRM_GPS_APPROVAL',
        ])->delete();
    }
};
