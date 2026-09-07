<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-09-05 (screenshot sidebar Sales): "di menu sales ga
// boleh ada menu group laporan, semua laporan masuk ke menu-menu group
// atasnya" -- pola SAMA dgn Procurement (lihat
// 2026_09_05_000008_regroup_procurement_menu_by_stage.php), SENGAJA
// menyimpang dari docs/sidebarmenustandard.md §1/§7. Beda dari Procurement:
// Sales sudah punya 3 grup tahap alur (Inquiry & Quotation/Sales Order/
// Delivery, id 938/939/940) -- tidak perlu pecah/buat grup baru, cukup
// pindahkan 4 item dari grup "Laporan" (id 180=SALES_MON) ke grup tahap yang
// paling relevan isi laporannya, lalu hapus grup 180 (sudah kosong, tidak
// ada tabel lain yg mereferensikan id-nya -- dicek sys_user_menu/
// sys_app_menu_color, keduanya tidak ber-FK ke sys_app_menu.id).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');

        // Win-Rate Sales -> Inquiry & Quotation (938): laporan ini soal hasil
        // konversi Inquiry (CONVERTED/LOST), bukan soal Sales Order/Delivery.
        $db->table('sys_app_menu')->where('code', 'SALES_WINRATE')->update(['parent_id' => 938, 'sort_order' => 60]);

        // Daftar Sales Order / Monitoring Order / Laporan Penjualan -> Sales
        // Order (939): ketiganya laporan soal status/rekap Customer Order.
        $db->table('sys_app_menu')->where('code', 'SALES_GRID_SO')->update(['parent_id' => 939, 'sort_order' => 30]);
        $db->table('sys_app_menu')->where('code', 'SALES_MON_ORD')->update(['parent_id' => 939, 'sort_order' => 40]);
        $db->table('sys_app_menu')->where('code', 'SALES_RPT')->update(['parent_id' => 939, 'sort_order' => 50]);

        $db->table('sys_app_menu')->where('id', 180)->delete();
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')->insert([
            'id' => 180,
            'parent_id' => 1,
            'code' => 'SALES_MON',
            'label' => 'Laporan',
            'menu_type' => 'group',
            'target_kind' => 'app',
            'sort_order' => 80,
            'activate' => 1,
            'status_code' => 'NOT_BUILT',
            'created_at' => '2026-08-08 17:23:31',
        ]);

        $db->table('sys_app_menu')->where('code', 'SALES_WINRATE')->update(['parent_id' => 180, 'sort_order' => 15]);
        $db->table('sys_app_menu')->where('code', 'SALES_GRID_SO')->update(['parent_id' => 180, 'sort_order' => 10]);
        $db->table('sys_app_menu')->where('code', 'SALES_MON_ORD')->update(['parent_id' => 180, 'sort_order' => 20]);
        $db->table('sys_app_menu')->where('code', 'SALES_RPT')->update(['parent_id' => 180, 'sort_order' => 30]);
    }
};
