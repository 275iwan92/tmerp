<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-08-28: Dispatch (dibangun sesi ini sbg modul top-level
// terpisah, migration 2026_08_28_000012) sebenarnya SUDAH punya slot resmi --
// SALES_DO "Delivery Order" (id 116, NOT_BUILT) di grup Sales > "Delivery"
// (SALES_DLVGRP, dibuat migration 2026_08_22_000003) -- cuma dilupakan waktu
// Dispatch dibangun. User sebut fiturnya "Delivery" (kepanjangan Delivery
// Order/Delivery Notes/Surat Jalan), bukan "Dispatch". Konsolidasi: pakai slot
// SALES_DO yang sudah ada, buang modul DISPATCH yang nyasar.
//
// DO+Surat Jalan tetap 1 tabel 2 cetakan (dispatch_tier1-2.md) -- tidak perlu
// 2 menu item terpisah, sama pola Quotation/PR/dst yang print-nya tombol di
// dalam Entry, bukan menu sendiri.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $dispatchModuleId = $sys->table('sec_module')->where('module_code', 'DISPATCH')->value('id_module');

        if ($dispatchModuleId) {
            // OR-kan flag dgn permission SALES (module_id=1) yg sudah ada -- jangan
            // downgrade grup yg kebetulan sudah punya akses lebih longgar.
            $dispatchPerms = $sys->table('sec_group_permission')->where('module_id', $dispatchModuleId)->get();

            foreach ($dispatchPerms as $p) {
                $existing = $sys->table('sec_group_permission')
                    ->where('group_id', $p->group_id)->where('module_id', 1)->first();

                $sys->table('sec_group_permission')->updateOrInsert(
                    ['group_id' => $p->group_id, 'module_id' => 1],
                    [
                        'can_access' => ($existing->can_access ?? false) || $p->can_access,
                        'can_input' => ($existing->can_input ?? false) || $p->can_input,
                        'can_approve' => ($existing->can_approve ?? false) || $p->can_approve,
                        'can_export' => ($existing->can_export ?? false) || $p->can_export,
                        'updated_at' => now(),
                        'created_at' => $existing->created_at ?? now(),
                    ]
                );
            }

            $sys->table('sec_group_permission')->where('module_id', $dispatchModuleId)->delete();
            $sys->table('sec_module')->where('id_module', $dispatchModuleId)->delete();
        }

        $sys->table('sys_app_menu')->where('code', 'DISPATCH_DO')->delete();
        $sys->table('sys_app_menu')->where('code', 'DISPATCH')->delete();

        $sys->table('sys_app_menu')->where('code', 'SALES_DO')->update([
            'object_type' => 'grid',
            'target' => null,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'SALES_DO')->update([
            'object_type' => 'form',
            'target' => 'form_delivery_order.php',
            'status_code' => 'NOT_BUILT',
            'status_updated_by' => null,
            'status_updated_at' => null,
        ]);

        $sys->table('sys_app_menu')->insert([
            'id' => 987,
            'parent_id' => null,
            'code' => 'DISPATCH',
            'label' => 'Dispatch',
            'menu_type' => 'module',
            'required_object' => 'DISPATCH',
            'sort_order' => 65,
            'activate' => true,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);

        $sys->table('sys_app_menu')->insert([
            'id' => 988,
            'parent_id' => 987,
            'code' => 'DISPATCH_DO',
            'label' => 'Delivery Order',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'sort_order' => 10,
            'activate' => true,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);

        $dispatchModuleId = $sys->table('sec_module')->insertGetId([
            'module_code' => 'DISPATCH',
            'module_name' => 'Dispatch',
            'icon' => 'fa-solid fa-truck-fast',
            'route_prefix' => 'dispatch',
            'min_mgmt_level' => 1,
            'is_reportable' => true,
            'sort_order' => 65,
            'activate' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['SALES_STAFF', 'SALES_SPV', 'OPS_MGR', 'DIREKSI'] as $groupCode) {
            $groupId = $sys->table('sec_group')->where('group_code', $groupCode)->value('id_group');

            if ($groupId) {
                $sys->table('sec_group_permission')->updateOrInsert(
                    ['group_id' => $groupId, 'module_id' => $dispatchModuleId],
                    ['can_access' => true, 'can_input' => true, 'can_approve' => $groupCode !== 'SALES_STAFF', 'can_export' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        // Catatan: permission SALES (module_id=1) yg sempat di-OR-kan waktu up()
        // TIDAK di-downgrade balik di sini (lossy, berisiko turunkan akses yg
        // sudah dipakai fitur Sales lain). Kalau perlu revert total, cek manual.
    }
};
