<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Keputusan user 2026-09-03 (VITAL): "Plan Delivery" (SALES_PLAN) diganti nama
// "Customer Plan Delivery" (CPDEL) DAN dipindah dari grup "Delivery"
// (SALES_DLVGRP) ke grup "Sales Order" (SALES_ORDGRP) -- gabung dgn Customer
// Order, karena CPDEL adalah kelanjutan langsung CO (baris lahir dari CO
// Confirm via ENGINE, lihat co/entry.blade.php::confirmCo()). Grup "Delivery"
// yang ditinggalkan WAJIB diisi 2 item: Delivery Order (sudah ada, SALES_DO)
// + Delivery Notes (BARU, SALES_DN) -- Surat Jalan sekarang dapat slot menu
// sendiri (sebelumnya sengaja HANYA tombol print di dalam DO Entry, migration
// move_dispatch_menu_into_sales_delivery 2026-08-28 -- keputusan itu di-OVERRIDE
// eksplisit oleh user sesi ini). Delivery Notes utk skrng reuse grid Delivery
// Order yg sama (dispatch.do.index, 1 tabel 2 cetakan tetap berlaku) -- BELUM
// ada layar/filter terpisah, cek ke user dulu kalau mau dipisah sungguhan.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'SALES_PLAN')->update([
            'code' => 'SALES_CPDEL',
            'label' => 'Customer Plan Delivery',
            'parent_id' => 939, // SALES_ORDGRP
            'sort_order' => 20, // setelah Customer Order (10)
            'object_type' => 'grid',
            'target' => null,
        ]);

        $sys->table('sys_app_menu')->where('code', 'SALES_DO')->update(['sort_order' => 10]);

        $sys->table('sys_app_menu')->insert([
            'id' => 1029,
            'parent_id' => 940, // SALES_DLVGRP
            'code' => 'SALES_DN',
            'label' => 'Delivery Notes',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'icon' => 'fa-solid fa-file-lines',
            'target' => null,
            'sort_order' => 20,
            'activate' => true,
            'status_code' => 'READY_FOR_QC',
            'status_note' => 'Reuse grid Delivery Order (dispatch.do.index) -- 1 tabel 2 cetakan (DO + Surat Jalan), belum ada layar terpisah.',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'SALES_DN')->delete();

        $sys->table('sys_app_menu')->where('code', 'SALES_DO')->update(['sort_order' => 20]);

        $sys->table('sys_app_menu')->where('code', 'SALES_CPDEL')->update([
            'code' => 'SALES_PLAN',
            'label' => 'Plan Delivery',
            'parent_id' => 940,
            'sort_order' => 10,
            'object_type' => 'form',
            'target' => 'form_plan_delivery.php',
        ]);
    }
};
