<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Master Gaji & Pajak Pegawai -- ditaruh di grup "Payroll" (parent_id=974,
// sudah ada sejak dulu tapi cuma berisi placeholder "Perhitungan Gaji"
// NOT_BUILT) sort_order=5 (sebelum Perhitungan Gaji sort=10) karena setup
// gaji/PTKP/toggle BPJS-PPh21 harus ada duluan sebelum payroll bisa dijalankan.
return new class extends Migration
{
    public function up(): void
    {
        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        DB::connection('tm2sys')->table('sys_app_menu')->updateOrInsert(
            ['code' => 'HRM_PAYROLL_SALARY'],
            [
                'id' => $nextId,
                'parent_id' => 974,
                'label' => 'Master Gaji & Pajak Pegawai',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target_kind' => 'app',
                'sort_order' => 5,
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
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_PAYROLL_SALARY')->delete();
    }
};
