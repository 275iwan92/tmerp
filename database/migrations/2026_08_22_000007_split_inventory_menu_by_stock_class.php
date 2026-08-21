<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User menegaskan ulang (2026-08-22): sidebar Inventory harus dibagi
// #1 Input, #2 Laporan, dan MASING-MASING dibagi lagi jadi 3 MENU terpisah
// per kelas stok (RM / FG / SFG) -- bukan toggle di dalam satu halaman.
// Alasan eksplisit: recordnya memang masuk ke tabel fisik yang berbeda
// (rm_stockcard vs fg_stockcard vs sfg_stockcard, dst.), jadi strukturnya
// harus tercermin di menu, bukan disembunyikan di belakang satu layar.
//
// Shell cuma render 2 level (Modul -> Grup -> Item flat, TIDAK ada sub-grup
// bersarang -- lihat main-menu.blade.php sidebarGroups loop), jadi "3 bagian"
// di dalam Input/Laporan diwujudkan sbg 6 caption grup terpisah (bukan 2
// grup dgn sub-grup di dalamnya): "Input -- <kelas>" x3, "Laporan -- <kelas>" x3.
//
// Laporan: layar Kartu Stok & Master Stok TETAP 1 file Livewire (reuse
// kode, lihat stock-card.blade.php/master-stock.blade.php), cuma route-nya
// sekarang wajib {category} -- 3 menu item per layar, beda param category.
//
// Input: Stock In/Out/Return from Production/Stock Adjustment & Opname/
// Persetujuan Penyesuaian SEMUA masih NOT_BUILT -- ini murni restrukturisasi
// menu (belum ada layar sungguhan utk di-route), label sengaja disamakan
// lintas kelas utk sekarang (exact perbedaan alur per kelas, mis. apakah
// "Return from Production" relevan sama utk FG/SFG, perlu didiskusikan lagi
// saat benar-benar digarap -- jangan diasumsikan otomatis sama).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $nextId = (int) $db->table('sys_app_menu')->max('id') + 1;

        $classes = [
            'RM' => 'Bahan Baku & Bahan Pembantu',
            'FG' => 'Barang Jadi',
            'SFG' => 'Barang Setengah Jadi',
        ];

        // ================= LAPORAN: 3 grup x 2 item =================
        $rptSort = 40;
        foreach ($classes as $classCode => $classLabel) {
            $groupCode = "INV_RPT_{$classCode}";
            if (! $db->table('sys_app_menu')->where('code', $groupCode)->exists()) {
                $db->table('sys_app_menu')->insert([
                    'id' => $nextId++,
                    'parent_id' => 3, // module INV
                    'code' => $groupCode,
                    'label' => "Laporan — {$classLabel}",
                    'menu_type' => 'group',
                    'sort_order' => $rptSort,
                    'is_active' => true,
                    'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => 'claude_code',
                    'status_updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
            $groupId = $db->table('sys_app_menu')->where('code', $groupCode)->value('id');

            // object_type disamakan dgn item lama sblm di-retire: Master Stok='grid'
            // (ikon list), Kartu Stok='report' (ikon chart) -- lihat iconFor() di main-menu.blade.php.
            $items = [
                ["INV_RAW_{$classCode}", 'Master Stok', 'grid', 10],
                ["INV_CARD_{$classCode}", 'Kartu Stok', 'report', 20],
            ];
            foreach ($items as [$itemCode, $itemLabel, $objType, $itemSort]) {
                if (! $db->table('sys_app_menu')->where('code', $itemCode)->exists()) {
                    $db->table('sys_app_menu')->insert([
                        'id' => $nextId++,
                        'parent_id' => $groupId,
                        'code' => $itemCode,
                        'label' => $itemLabel,
                        'menu_type' => 'item',
                        'object_type' => $objType,
                        'sort_order' => $itemSort,
                        'is_active' => true,
                        'status_code' => 'READY_FOR_QC',
                        'status_updated_by' => 'claude_code',
                        'status_updated_at' => now(),
                        'created_at' => now(),
                    ]);
                }
            }
            $rptSort += 10;
        }

        // ================= INPUT: 3 grup x 5 item =================
        $trxSort = 10;
        $trxItems = [
            'Stock In',
            'Stock Out',
            'Return from Production',
            'Stock Adjustment & Opname',
            'Persetujuan Penyesuaian',
        ];
        foreach ($classes as $classCode => $classLabel) {
            $groupCode = "INV_TRX_{$classCode}";
            if (! $db->table('sys_app_menu')->where('code', $groupCode)->exists()) {
                $db->table('sys_app_menu')->insert([
                    'id' => $nextId++,
                    'parent_id' => 3,
                    'code' => $groupCode,
                    'label' => "Input — {$classLabel}",
                    'menu_type' => 'group',
                    'sort_order' => $trxSort,
                    'is_active' => true,
                    'status_code' => 'NOT_BUILT',
                    'status_updated_by' => 'claude_code',
                    'status_updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
            $groupId = $db->table('sys_app_menu')->where('code', $groupCode)->value('id');

            $itemSort = 10;
            foreach ($trxItems as $i => $label) {
                $itemCode = "INV_{$classCode}_ITEM".($i + 1);
                if (! $db->table('sys_app_menu')->where('code', $itemCode)->exists()) {
                    $db->table('sys_app_menu')->insert([
                        'id' => $nextId++,
                        'parent_id' => $groupId,
                        'code' => $itemCode,
                        'label' => $label,
                        'menu_type' => 'item',
                        'object_type' => 'form',
                        'sort_order' => $itemSort,
                        'is_active' => true,
                        'status_code' => 'NOT_BUILT',
                        'status_updated_by' => 'claude_code',
                        'status_updated_at' => now(),
                        'created_at' => now(),
                    ]);
                }
                $itemSort += 10;
            }
            $trxSort += 10;
        }

        // ================= Retire grup & item lama =================
        $db->table('sys_app_menu')->whereIn('code', [
            'INV_TRX', 'INV_RPT', 'INV_RAW', 'INV_CARD',
            'INV_IN', 'INV_OUT', 'INV_RTP', 'INV_ADJ2', 'INV_APRV',
        ])->update(['is_active' => false]);
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        $classes = ['RM', 'FG', 'SFG'];
        foreach ($classes as $c) {
            $db->table('sys_app_menu')->whereIn('code', ["INV_RAW_{$c}", "INV_CARD_{$c}"])->delete();
            $db->table('sys_app_menu')->where('code', "INV_RPT_{$c}")->delete();

            for ($i = 1; $i <= 5; $i++) {
                $db->table('sys_app_menu')->where('code', "INV_{$c}_ITEM{$i}")->delete();
            }
            $db->table('sys_app_menu')->where('code', "INV_TRX_{$c}")->delete();
        }

        $db->table('sys_app_menu')->whereIn('code', [
            'INV_TRX', 'INV_RPT', 'INV_RAW', 'INV_CARD',
            'INV_IN', 'INV_OUT', 'INV_RTP', 'INV_ADJ2', 'INV_APRV',
        ])->update(['is_active' => true]);
    }
};
