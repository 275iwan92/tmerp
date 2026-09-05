<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-09-05 (screenshot sidebar Procurement): pecah grup
// "Transaksi" tunggal jadi 4 grup per-tahap alur (Requisition/Order/Receive/
// Return), dan HAPUS grup "Laporan" terpisah -- tiap laporan Outstanding
// (PR/PO/PV) pindah masuk ke grup tahap yang sesuai. Ini SENGAJA menyimpang
// dari pola default docs/sidebarmenustandard.md §1/§7 ("wajib tepat 1 grup
// Laporan berlabel 'Laporan', jangan campur laporan ke grup transaksi") --
// instruksi eksplisit user via gambar, bukan keputusan sepihak. Grup 210
// (dulu "Transaksi") & 280 (dulu "Laporan") DIPAKAI ULANG jadi "Requisition"
// & "Order" (id tetap sama, cuma code+label+isi berubah) supaya id existing
// tidak orphan; 2 grup baru (Receive/Return) ditambah dengan MAX(id)+1/+2.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');

        $maxId = (int) $db->table('sys_app_menu')->max('id');
        $receiveGroupId = $maxId + 1;
        $returnGroupId = $maxId + 2;

        // Pakai ulang grup 210 ("Transaksi") -> "Requisition"
        $db->table('sys_app_menu')->where('id', 210)->update([
            'code' => 'PROC_GRP_REQUISITION',
            'label' => 'Requisition',
            'sort_order' => 10,
        ]);

        // Pakai ulang grup 280 ("Laporan") -> "Order"
        $db->table('sys_app_menu')->where('id', 280)->update([
            'code' => 'PROC_GRP_ORDER',
            'label' => 'Order',
            'sort_order' => 20,
        ]);

        $db->table('sys_app_menu')->insert([
            [
                'id' => $receiveGroupId,
                'parent_id' => 2, // PROC
                'code' => 'PROC_GRP_RECEIVE',
                'label' => 'Receive',
                'menu_type' => 'group',
                'target_kind' => 'app',
                'sort_order' => 30,
                'activate' => 1,
                'status_code' => 'NOT_BUILT',
                'created_at' => now(),
            ],
            [
                'id' => $returnGroupId,
                'parent_id' => 2, // PROC
                'code' => 'PROC_GRP_RETURN',
                'label' => 'Return',
                'menu_type' => 'group',
                'target_kind' => 'app',
                'sort_order' => 40,
                'activate' => 1,
                'status_code' => 'NOT_BUILT',
                'created_at' => now(),
            ],
        ]);

        // Requisition (210): PR + PR Approval + Outstanding PR
        $db->table('sys_app_menu')->where('code', 'PROC_PR')->update(['parent_id' => 210, 'sort_order' => 10]);
        $db->table('sys_app_menu')->where('code', 'PROC_PR_APPR')->update(['parent_id' => 210, 'sort_order' => 20]);
        $db->table('sys_app_menu')->where('code', 'PROC_MON_PR')->update(['parent_id' => 210, 'sort_order' => 30]);

        // Order (280): Quotation + PO + PO Approval + Outstanding PO
        $db->table('sys_app_menu')->where('code', 'PROC_PQ')->update(['parent_id' => 280, 'sort_order' => 10]);
        $db->table('sys_app_menu')->where('code', 'PROC_PO')->update(['parent_id' => 280, 'sort_order' => 20]);
        $db->table('sys_app_menu')->where('code', 'PROC_PO_APPR')->update(['parent_id' => 280, 'sort_order' => 30]);
        $db->table('sys_app_menu')->where('code', 'PROC_MON_PO')->update(['parent_id' => 280, 'sort_order' => 40]);

        // Receive (baru): Purchase Receive + Outstanding PV
        $db->table('sys_app_menu')->where('code', 'PROC_RCV')->update(['parent_id' => $receiveGroupId, 'sort_order' => 10]);
        $db->table('sys_app_menu')->where('code', 'PROC_MON_PV')->update(['parent_id' => $receiveGroupId, 'sort_order' => 20]);

        // Return (baru): Purchase Return + Persetujuan Purchase Return
        $db->table('sys_app_menu')->where('code', 'PROC_RET')->update(['parent_id' => $returnGroupId, 'sort_order' => 10]);
        $db->table('sys_app_menu')->where('code', 'PROC_RET_APPR')->update(['parent_id' => $returnGroupId, 'sort_order' => 20]);
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')->where('code', 'PROC_GRP_RECEIVE')->delete();
        $db->table('sys_app_menu')->where('code', 'PROC_GRP_RETURN')->delete();

        $db->table('sys_app_menu')->where('id', 210)->update([
            'code' => 'PROC_TRX',
            'label' => 'Transaksi',
            'sort_order' => 10,
        ]);
        $db->table('sys_app_menu')->where('id', 280)->update([
            'code' => 'PROC_MON',
            'label' => 'Laporan',
            'sort_order' => 80,
        ]);

        $db->table('sys_app_menu')->where('code', 'PROC_PR')->update(['parent_id' => 210, 'sort_order' => 10]);
        $db->table('sys_app_menu')->where('code', 'PROC_PR_APPR')->update(['parent_id' => 210, 'sort_order' => 15]);
        $db->table('sys_app_menu')->where('code', 'PROC_PQ')->update(['parent_id' => 210, 'sort_order' => 20]);
        $db->table('sys_app_menu')->where('code', 'PROC_PO')->update(['parent_id' => 210, 'sort_order' => 30]);
        $db->table('sys_app_menu')->where('code', 'PROC_PO_APPR')->update(['parent_id' => 210, 'sort_order' => 35]);
        $db->table('sys_app_menu')->where('code', 'PROC_RCV')->update(['parent_id' => 210, 'sort_order' => 40]);
        $db->table('sys_app_menu')->where('code', 'PROC_RET')->update(['parent_id' => 210, 'sort_order' => 50]);
        $db->table('sys_app_menu')->where('code', 'PROC_RET_APPR')->update(['parent_id' => 210, 'sort_order' => 55]);

        $db->table('sys_app_menu')->where('code', 'PROC_MON_PR')->update(['parent_id' => 280, 'sort_order' => 10]);
        $db->table('sys_app_menu')->where('code', 'PROC_MON_PO')->update(['parent_id' => 280, 'sort_order' => 20]);
        $db->table('sys_app_menu')->where('code', 'PROC_MON_PV')->update(['parent_id' => 280, 'sort_order' => 30]);
    }
};
