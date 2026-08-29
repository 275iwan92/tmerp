<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Restrukturisasi sidebar HRM atas instruksi user 2026-08-29: 5 grup top-level
// (caption SELALU Bahasa Inggris per instruksi eksplisit) -- Organization,
// Employee, Payroll, Time Attendance, Recruitment. Item di dalamnya TETAP
// Bahasa Indonesia (konsisten konvensi existing, cuma caption grup yang
// diinggriskan). Shift & Lembur masuk Time Attendance (instruksi eksplisit).
//
// Reuse group id yang sudah ada (bukan bikin baru + orphan lama):
// - HRM_MASTER (922, dulu "Master Data") -> relabel "Employee", tarik
//   HRM_CORRECTION & HRM_SELFSERVICE masuk ke sini (dulu flat langsung di HRM).
// - HRM_PAYROLL (974, dulu item flat "Payroll") -> dikonversi jadi grup
//   (pola sama HRM_RECRUITMENT di 2026_08_29_000002), child-nya 1 item
//   placeholder mewarisi label lama.
// - HRM_SCHEDULE (924, dulu "Penjadwalan") -> relabel "Time Attendance",
//   tarik HRM_ATTENDANCE & HRM_LEAVE_APPROVAL masuk (dulu flat), tambah
//   placeholder baru "Lembur".
// - HRM_ORG baru (grup "Organization") -- belum ada layar org structure sama
//   sekali di sys_app_menu manapun (dicek dulu, MDATA cuma load Customer/
//   Supplier/Produk/dst, MD_SHIFT = master shift generic beda dari Shift
//   Roster HRM), jadi placeholder murni.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_app_menu')->where('code', 'HRM_ORG')->exists()) {
            return;
        }

        // ── Employee (922, ex "Master Data") ──
        $sys->table('sys_app_menu')->where('code', 'HRM_MASTER')->update(['label' => 'Employee', 'sort_order' => 10]);
        $employeeGroupId = $sys->table('sys_app_menu')->where('code', 'HRM_MASTER')->value('id');
        $sys->table('sys_app_menu')->where('code', 'HRM_EMPLOYEE')->update(['sort_order' => 10]);
        $sys->table('sys_app_menu')->where('code', 'HRM_CORRECTION')->update(['parent_id' => $employeeGroupId, 'sort_order' => 20]);
        $sys->table('sys_app_menu')->where('code', 'HRM_SELFSERVICE')->update(['parent_id' => $employeeGroupId, 'sort_order' => 30]);

        // ── Time Attendance (924, ex "Penjadwalan") ──
        $sys->table('sys_app_menu')->where('code', 'HRM_SCHEDULE')->update(['label' => 'Time Attendance', 'sort_order' => 20]);
        $attendanceGroupId = $sys->table('sys_app_menu')->where('code', 'HRM_SCHEDULE')->value('id');
        $sys->table('sys_app_menu')->where('code', 'HRM_ROSTER')->update(['sort_order' => 10]);
        $sys->table('sys_app_menu')->where('code', 'HRM_ATTENDANCE')->update(['parent_id' => $attendanceGroupId, 'sort_order' => 20]);
        $sys->table('sys_app_menu')->where('code', 'HRM_LEAVE_APPROVAL')->update(['parent_id' => $attendanceGroupId, 'sort_order' => 30]);

        // ── Payroll (974, ex item flat) -> jadi grup ──
        $sys->table('sys_app_menu')->where('code', 'HRM_PAYROLL')->update([
            'menu_type' => 'group',
            'object_type' => null,
            'sort_order' => 15,
        ]);
        $payrollGroupId = $sys->table('sys_app_menu')->where('code', 'HRM_PAYROLL')->value('id');

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId++,
            'parent_id' => $payrollGroupId,
            'code' => 'HRM_PAYROLL_CALC',
            'label' => 'Perhitungan Gaji',
            'menu_type' => 'item',
            'object_type' => 'form',
            'target_kind' => 'app',
            'sort_order' => 10,
            'activate' => true,
            'status_code' => 'NOT_BUILT',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);

        // ── Lembur (Overtime) placeholder, Time Attendance ──
        $sys->table('sys_app_menu')->insert([
            'id' => $nextId++,
            'parent_id' => $attendanceGroupId,
            'code' => 'HRM_OVERTIME',
            'label' => 'Lembur',
            'menu_type' => 'item',
            'object_type' => 'form',
            'target_kind' => 'app',
            'sort_order' => 40,
            'activate' => true,
            'status_code' => 'NOT_BUILT',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);

        // ── Organization (grup baru) ──
        $hrmId = $sys->table('sys_app_menu')->where('code', 'HRM')->value('id');

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId++,
            'parent_id' => $hrmId,
            'code' => 'HRM_ORG',
            'label' => 'Organization',
            'menu_type' => 'group',
            'object_type' => null,
            'target_kind' => 'app',
            'sort_order' => 5,
            'activate' => true,
            'created_at' => now(),
        ]);
        $orgGroupId = $sys->table('sys_app_menu')->where('code', 'HRM_ORG')->value('id');

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId++,
            'parent_id' => $orgGroupId,
            'code' => 'HRM_ORG_STRUCTURE',
            'label' => 'Departemen & Posisi',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target_kind' => 'app',
            'sort_order' => 10,
            'activate' => true,
            'status_code' => 'NOT_BUILT',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->whereIn('code', ['HRM_ORG_STRUCTURE', 'HRM_ORG', 'HRM_OVERTIME', 'HRM_PAYROLL_CALC'])->delete();

        $sys->table('sys_app_menu')->where('code', 'HRM_PAYROLL')->update(['menu_type' => 'item', 'object_type' => 'form', 'sort_order' => 40]);

        $hrmId = $sys->table('sys_app_menu')->where('code', 'HRM')->value('id');
        $sys->table('sys_app_menu')->where('code', 'HRM_ATTENDANCE')->update(['parent_id' => $hrmId, 'sort_order' => 10]);
        $sys->table('sys_app_menu')->where('code', 'HRM_LEAVE_APPROVAL')->update(['parent_id' => $hrmId, 'sort_order' => 56]);
        $sys->table('sys_app_menu')->where('code', 'HRM_SCHEDULE')->update(['label' => 'Penjadwalan', 'sort_order' => 20]);

        $sys->table('sys_app_menu')->where('code', 'HRM_CORRECTION')->update(['parent_id' => $hrmId, 'sort_order' => 55]);
        $sys->table('sys_app_menu')->where('code', 'HRM_SELFSERVICE')->update(['parent_id' => $hrmId, 'sort_order' => 60]);
        $sys->table('sys_app_menu')->where('code', 'HRM_MASTER')->update(['label' => 'Master Data', 'sort_order' => 10]);
    }
};
