<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Grup "Laporan" baru di bawah modul HRM (SIDEBAR_MENU_STANDARD.md #1 --
// layar read-only/monitoring wajib dikelompokkan eksplisit, walau baru 1
// item) + leaf "Time Attendance" -> pages::hrm.attendance.index. Profil
// kehadiran per karyawan (hrm.attendance.profile) diakses lewat drill-down
// double-click dari grid ini, BUKAN item menu terpisah -- sama seperti pola
// Master Employee -> detail. Lihat HRM_STATE.yaml -> time_attendance.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $hrmMenuId = $sys->table('sys_app_menu')->where('code', 'HRM')->value('id');

        if (! $hrmMenuId) {
            return;
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        if (! $sys->table('sys_app_menu')->where('code', 'HRM_REPORT')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => $hrmMenuId,
                'code' => 'HRM_REPORT',
                'label' => 'Laporan',
                'menu_type' => 'group',
                'object_type' => null,
                'target_kind' => 'app',
                'sort_order' => 30,
                'is_active' => true,
                'created_at' => now(),
            ]);
        }

        $reportId = $sys->table('sys_app_menu')->where('code', 'HRM_REPORT')->value('id');

        if (! $sys->table('sys_app_menu')->where('code', 'HRM_ATTENDANCE')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => $reportId,
                'code' => 'HRM_ATTENDANCE',
                'label' => 'Time Attendance',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target_kind' => 'app',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => now(),
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['HRM_ATTENDANCE', 'HRM_REPORT'])->delete();
    }
};
