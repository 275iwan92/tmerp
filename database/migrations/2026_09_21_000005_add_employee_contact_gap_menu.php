<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Laporan kelengkapan nomor WA -- ditaruh di grup Employee (bukan Payroll)
// karena yang dikejar data masternya, bukan periode gajinya.
return new class extends Migration
{
    private const GROUP_EMPLOYEE = 922;

    public function up(): void
    {
        // sys_app_menu.id bukan AUTO_INCREMENT -- id harus ditentukan sendiri.
        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        DB::connection('tm2sys')->table('sys_app_menu')->updateOrInsert(
            ['code' => 'HRM_CONTACT_GAP'],
            [
                'id' => $nextId,
                'parent_id' => self::GROUP_EMPLOYEE,
                'label' => 'Karyawan Tanpa No. WhatsApp',
                'menu_type' => 'item',
                'object_type' => 'report',
                'target_kind' => 'app',
                'sort_order' => 40,
                'activate' => 1,
                'status_code' => 'READY_FOR_QC',
            ]
        );
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_CONTACT_GAP')->delete();
    }
};
