<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Officer HR mengerjakan payroll sebagai 1 rantai berurutan (upload presensi ->
// review presensi -> hitung gaji -> cetak slip -> transfer bank -> roster cash),
// tapi 2 langkah pertama dulu duduk di grup "Time Attendance" sehingga rantainya
// terputus antar-grup. Semua langkah proses dikumpulkan ke grup Payroll, master
// (setup) didorong ke bawah rantai supaya yang terbaca duluan alur kerjanya.
// sort_order 40 SENGAJA dikosongkan = slot "Cetak Slip Gaji" (mockup_04) yang
// layar daftarnya belum ada.
return new class extends Migration
{
    private const GROUP_PAYROLL = 974;

    private const GROUP_TIME_ATTENDANCE = 924;

    /** code => sort_order baru di dalam grup Payroll */
    private const SEQUENCE = [
        'HRM_ATTENDANCE_UPLOAD' => 10,
        'HRM_ATTENDANCE' => 20,
        'HRM_PAYROLL_CALC' => 30,
        'HRM_PAYROLL_BANKXFER' => 50,
        'HRM_PAYROLL_CASHROSTER' => 60,
        'HRM_PAYROLL_SALARY' => 70,
        'HRM_PAYROLL_BANK' => 80,
    ];

    /** Posisi asal, untuk down() */
    private const PREVIOUS = [
        'HRM_ATTENDANCE_UPLOAD' => [self::GROUP_TIME_ATTENDANCE, 15],
        'HRM_ATTENDANCE' => [self::GROUP_TIME_ATTENDANCE, 20],
        'HRM_PAYROLL_CALC' => [self::GROUP_PAYROLL, 10],
        'HRM_PAYROLL_BANKXFER' => [self::GROUP_PAYROLL, 20],
        'HRM_PAYROLL_CASHROSTER' => [self::GROUP_PAYROLL, 30],
        'HRM_PAYROLL_SALARY' => [self::GROUP_PAYROLL, 5],
        'HRM_PAYROLL_BANK' => [self::GROUP_PAYROLL, 7],
    ];

    public function up(): void
    {
        foreach (self::SEQUENCE as $code => $sortOrder) {
            DB::connection('tm2sys')->table('sys_app_menu')
                ->where('code', $code)
                ->update(['parent_id' => self::GROUP_PAYROLL, 'sort_order' => $sortOrder]);
        }
    }

    public function down(): void
    {
        foreach (self::PREVIOUS as $code => [$parentId, $sortOrder]) {
            DB::connection('tm2sys')->table('sys_app_menu')
                ->where('code', $code)
                ->update(['parent_id' => $parentId, 'sort_order' => $sortOrder]);
        }
    }
};
