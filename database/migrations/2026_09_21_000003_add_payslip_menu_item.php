<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Slot ke-4 rantai payroll (lihat 2026_09_21_000001) akhirnya terisi: sebelum
// ini route cetak slip gaji tidak dirujuk dari layar mana pun, jadi officer
// tidak punya jalan apa pun untuk mencetak slip.
return new class extends Migration
{
    private const GROUP_PAYROLL = 974;

    public function up(): void
    {
        // sys_app_menu.id bukan AUTO_INCREMENT -- id harus ditentukan sendiri.
        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        DB::connection('tm2sys')->table('sys_app_menu')->updateOrInsert(
            ['code' => 'HRM_PAYROLL_PAYSLIP'],
            [
                'id' => $nextId,
                'parent_id' => self::GROUP_PAYROLL,
                'label' => 'Cetak & Kirim Slip Gaji',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target_kind' => 'app',
                'sort_order' => 40,
                'activate' => 1,
                'status_code' => 'READY_FOR_QC',
            ]
        );
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_PAYROLL_PAYSLIP')->delete();
    }
};
