<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menambah 4 "macam laporan" baru (Akuntansi/Produksi/SDM/Gudang) ke grup
// "Laporan" (MGMT_LAPORAN, id 1048) yang sudah ada -- permintaan user
// 2026-09-13: "saya maunya laporan itu ada berbagai macam", menunjuk mockup
// referensi bergrup Executive/Purchasing/Sales/Accounting/Manufacturing/HR.
//
// TEMUAN NYATA sebelum migration ini ditulis: 3 item menu yang SUDAH ADA
// (MGMT_EXEC/MGMT_SALES/MGMT_PURCHASE, id 1049-1051) TIDAK PERNAH dipetakan
// ke route di main-menu.blade.php::itemRoutes() -- itu sebab keluhan user
// "tidak ada menu di panel kiri" (menunya ADA & tampil di sidebar krn
// module:MGMT sudah live, tapi diklik tidak ke mana-mana / placeholder
// kosong). Diperbaiki di file itu langsung (bukan di migration ini).
return new class extends Migration
{
    private const ITEMS = [
        ['code' => 'MGMT_ACCOUNTING', 'label' => 'Akuntansi & Keuangan', 'sort_order' => 30],
        ['code' => 'MGMT_PRODUCTION', 'label' => 'Produksi & Manufaktur', 'sort_order' => 40],
        ['code' => 'MGMT_HR', 'label' => 'SDM & Payroll', 'sort_order' => 50],
        ['code' => 'MGMT_WAREHOUSE', 'label' => 'Gudang & Inventori', 'sort_order' => 60],
    ];

    public function up(): void
    {
        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        foreach (self::ITEMS as $item) {
            DB::connection('tm2sys')->table('sys_app_menu')->updateOrInsert(
                ['code' => $item['code']],
                [
                    'id' => $nextId++,
                    'parent_id' => 1048, // MGMT_LAPORAN
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
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', array_column(self::ITEMS, 'code'))
            ->delete();
    }
};
