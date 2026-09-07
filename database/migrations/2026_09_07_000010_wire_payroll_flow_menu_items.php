<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Sisa layar flow Payroll (03/05/06/08 dari checklist mockup) -- menu
// diurutkan SEIRAMA urutan proses (permintaan user 2026-09-07): grup Time
// Attendance (924) = presensi dulu, baru grup Payroll (974) = kalkulasi->
// transfer/cash. HRM_PAYROLL_CALC ("Perhitungan Gaji") SUDAH ADA sbg
// placeholder sejak lama -- tinggal diaktifkan, bukan dibuat baru. Slip Gaji
// TIDAK dapat menu sendiri (dibuka kontekstual dari baris payroll run,
// pola sama Nota CO yang juga tidak ada di menu -- lihat co/print.blade.php).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'HRM_PAYROLL_CALC')
            ->update(['status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude_code', 'status_updated_at' => now()]);

        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        $items = [
            ['code' => 'HRM_DISCIPLINE_REPORT', 'parent_id' => 924, 'label' => 'Laporan Kedisiplinan Harian', 'sort_order' => 25],
            ['code' => 'HRM_PAYROLL_BANKXFER', 'parent_id' => 974, 'label' => 'Generator Transfer Bank', 'sort_order' => 20],
            ['code' => 'HRM_PAYROLL_CASHROSTER', 'parent_id' => 974, 'label' => 'Roster Cash & Pecahan Uang', 'sort_order' => 30],
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

        // Time Attendance (924) harus tampil SEBELUM Payroll (974) di sidebar
        // -- urutan proses: presensi dulu baru payroll. Sebelumnya kebalik
        // (Payroll sort=15 < Time Attendance sort=20).
        DB::connection('tm2sys')->table('sys_app_menu')->where('id', 924)->update(['sort_order' => 15]);
        DB::connection('tm2sys')->table('sys_app_menu')->where('id', 974)->update(['sort_order' => 20]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', [
            'HRM_DISCIPLINE_REPORT', 'HRM_PAYROLL_BANKXFER', 'HRM_PAYROLL_CASHROSTER',
        ])->delete();

        DB::connection('tm2sys')->table('sys_app_menu')->where('id', 924)->update(['sort_order' => 20]);
        DB::connection('tm2sys')->table('sys_app_menu')->where('id', 974)->update(['sort_order' => 15]);

        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'HRM_PAYROLL_CALC')
            ->update(['status_code' => 'NOT_BUILT', 'status_updated_by' => null, 'status_updated_at' => null]);
    }
};
