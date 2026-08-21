<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Sidebar Sales "TRANSAKSI" makin panjang (Inquiry Product, Imposition Engine,
// Product Costing, Quotation, Customer Order, Plan Delivery, Delivery Order --
// 7 item rata). User minta dipecah jadi 3 sub-grup supaya lebih ringkas dibaca:
// "Inquiry & Quotation" (proses sebelum order jadi), "Sales Order" (order
// sudah confirmed), "Delivery" (pengiriman). Grup lama SALES_TRX di-retire
// (is_active=false), bukan dihapus -- sama pola retire menu lain sesi ini.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');

        // sys_app_menu.id BUKAN auto_increment (PK manual, alokasi MAX(id)+1
        // -- lihat catatan session sebelumnya), jadi id harus disuplai eksplisit.
        $nextId = (int) $db->table('sys_app_menu')->max('id') + 1;

        // sort_order < SALES_MON (80) supaya grup transaksi tetap di atas
        // "Monitoring & Laporan" di sidebar (bukan di bawahnya).
        $groups = [
            ['code' => 'SALES_INQGRP', 'label' => 'Inquiry & Quotation', 'sort_order' => 20],
            ['code' => 'SALES_ORDGRP', 'label' => 'Sales Order', 'sort_order' => 30],
            ['code' => 'SALES_DLVGRP', 'label' => 'Delivery', 'sort_order' => 40],
        ];

        foreach ($groups as $g) {
            if ($db->table('sys_app_menu')->where('code', $g['code'])->exists()) {
                continue;
            }

            $db->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => 1, // module SALES
                'code' => $g['code'],
                'label' => $g['label'],
                'menu_type' => 'group',
                'sort_order' => $g['sort_order'],
                'is_active' => true,
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        $inqGrpId = $db->table('sys_app_menu')->where('code', 'SALES_INQGRP')->value('id');
        $ordGrpId = $db->table('sys_app_menu')->where('code', 'SALES_ORDGRP')->value('id');
        $dlvGrpId = $db->table('sys_app_menu')->where('code', 'SALES_DLVGRP')->value('id');

        $reassign = [
            'SALES_INQ' => [$inqGrpId, 10],
            'SALES_IMPO' => [$inqGrpId, 20],
            'SALES_COST' => [$inqGrpId, 30],
            'SALES_QUOT' => [$inqGrpId, 40],
            'SALES_NOTA' => [$inqGrpId, 50], // retired/inactive, ikut dipindah biar rapi kalau diaktifkan lagi
            'SALES_SO' => [$ordGrpId, 10],
            'SALES_PLAN' => [$dlvGrpId, 10],
            'SALES_DO' => [$dlvGrpId, 20],
        ];

        foreach ($reassign as $code => [$parentId, $sort]) {
            $db->table('sys_app_menu')->where('code', $code)->update([
                'parent_id' => $parentId,
                'sort_order' => $sort,
            ]);
        }

        // Grup lama kosong sekarang -- retire, jangan dihapus.
        $db->table('sys_app_menu')->where('code', 'SALES_TRX')->update(['is_active' => false]);
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')->where('code', 'SALES_TRX')->update(['is_active' => true]);

        $trxId = $db->table('sys_app_menu')->where('code', 'SALES_TRX')->value('id');

        $restore = [
            'SALES_INQ' => 10, 'SALES_COST' => 20, 'SALES_QUOT' => 30, 'SALES_SO' => 40,
            'SALES_PLAN' => 50, 'SALES_DO' => 60, 'SALES_IMPO' => 70, 'SALES_NOTA' => 80,
        ];
        foreach ($restore as $code => $sort) {
            $db->table('sys_app_menu')->where('code', $code)->update(['parent_id' => $trxId, 'sort_order' => $sort]);
        }

        $db->table('sys_app_menu')->whereIn('code', ['SALES_INQGRP', 'SALES_ORDGRP', 'SALES_DLVGRP'])->delete();
    }
};
