<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Laporan Slow Moving Item (2026-08-28, permintaan user) -- item ke-3 di
// tiap grup "Laporan -- <kelas>" (lihat 2026_08_22_000007_split_inventory_
// menu_by_stock_class.php utk 2 item pertama, Master Stok & Kartu Stok).
// Route target diresolusi via kode menu di main-menu.blade.php itemRoutes(),
// bukan kolom target di sini (pola sama seperti INV_RAW_*/INV_CARD_*).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $nextId = (int) $db->table('sys_app_menu')->max('id') + 1;

        $classes = ['RM', 'FG', 'SFG'];

        foreach ($classes as $classCode) {
            $groupId = $db->table('sys_app_menu')->where('code', "INV_RPT_{$classCode}")->value('id');

            if (! $groupId) {
                continue;
            }

            $itemCode = "INV_SLOW_{$classCode}";
            if (! $db->table('sys_app_menu')->where('code', $itemCode)->exists()) {
                $db->table('sys_app_menu')->insert([
                    'id' => $nextId++,
                    'parent_id' => $groupId,
                    'code' => $itemCode,
                    'label' => 'Slow Moving Item',
                    'menu_type' => 'item',
                    'object_type' => 'report',
                    'sort_order' => 30,
                    'activate' => true,
                    'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => 'claude_code',
                    'status_updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        foreach (['RM', 'FG', 'SFG'] as $classCode) {
            $db->table('sys_app_menu')->where('code', "INV_SLOW_{$classCode}")->delete();
        }
    }
};
