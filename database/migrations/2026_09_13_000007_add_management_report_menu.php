<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu "Laporan Manajemen" -- modul baru khusus Direksi/Top Management.
//
// Dibuat sbg MODUL tersendiri (bukan menempel di Sales/Procurement) karena
// isinya lintas modul: omset penjualan, pembelian, AR/AP, dan master sekaligus.
// Mengikuti sidebarmenustandard.md: tiap modul punya grup, dan ada grup
// "Laporan" yang nama itemnya mengikuti #[Title] layar aslinya.
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $sys = DB::connection('tm2sys');

        // sys_app_menu.id BUKAN auto-increment -- id diberikan manual, sama
        // seperti migration menu lain di proyek ini (lihat
        // 2026_09_09_000002_add_component_library_menu_item.php).
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $moduleId = $nextId++;
        $sys->table('sys_app_menu')->insert([
            'id' => $moduleId,
            'parent_id' => null,
            'code' => 'MGMT',
            'label' => 'Laporan Manajemen',
            'short_label' => 'MGT',
            'tooltip' => 'Ringkasan eksekutif lintas modul untuk Direksi',
            'icon' => 'fa-solid fa-chart-line',
            'menu_type' => 'module',
            'target_kind' => 'app',
            'sort_order' => 15,
            'activate' => 1,
            'required_object' => 'MGMT',
            'created_at' => $now,
            'status_code' => 'READY_FOR_QC',
            'doc_slug' => 'laporanmanajemen',
        ]);

        $groupId = $nextId++;
        $sys->table('sys_app_menu')->insert([
            'id' => $groupId,
            'parent_id' => $moduleId,
            'code' => 'MGMT_RINGKASAN',
            'label' => 'Ringkasan',
            'menu_type' => 'group',
            'target_kind' => 'app',
            'sort_order' => 10,
            'activate' => 1,
            'created_at' => $now,
            'status_code' => 'READY_FOR_QC',
        ]);

        $reportGroupId = $nextId++;
        $sys->table('sys_app_menu')->insert([
            'id' => $reportGroupId,
            'parent_id' => $moduleId,
            'code' => 'MGMT_LAPORAN',
            'label' => 'Laporan',
            'menu_type' => 'group',
            'target_kind' => 'app',
            'sort_order' => 20,
            'activate' => 1,
            'created_at' => $now,
            'status_code' => 'READY_FOR_QC',
        ]);

        $items = [
            [$groupId, 'MGMT_EXEC', 'Dashboard Eksekutif', 'dashboard', 'report.executive', 10, 'fa-solid fa-gauge-high'],
            [$reportGroupId, 'MGMT_SALES', 'Rincian Penjualan', 'report', 'report.sales', 10, 'fa-solid fa-file-invoice-dollar'],
            [$reportGroupId, 'MGMT_PURCHASE', 'Rincian Pembelian', 'report', 'report.purchase', 20, 'fa-solid fa-cart-shopping'],
        ];

        foreach ($items as [$parent, $code, $label, $objectType, $target, $order, $icon]) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => $parent,
                'code' => $code,
                'label' => $label,
                'icon' => $icon,
                'menu_type' => 'item',
                'object_type' => $objectType,
                'target' => $target,
                'target_kind' => 'app',
                'sort_order' => $order,
                'activate' => 1,
                'created_at' => $now,
                'status_code' => 'READY_FOR_QC',
            ]);
        }

        // Customer 360 sengaja TIDAK didaftarkan sbg item menu: layar itu selalu
        // dimasuki lewat klik dari laporan lain (butuh parameter customer),
        // bukan dibuka langsung dari sidebar.
    }

    public function down(): void
    {
        $ids = DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MGMT')->pluck('id');
        $childIds = DB::connection('tm2sys')->table('sys_app_menu')->whereIn('parent_id', $ids)->pluck('id');

        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('parent_id', $childIds)->delete();
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('parent_id', $ids)->delete();
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('id', $ids)->delete();
    }
};
