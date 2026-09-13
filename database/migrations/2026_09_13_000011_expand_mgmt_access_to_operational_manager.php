<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Perluasan akses Laporan Manajemen (keputusan user 2026-09-13, instruksi
// eksplisit): "kalau level Operational Manager keatas, default ke
// Management Report. Kalau kebawah, ya Operational Menu." -- ini
// membatalkan batasan Direksi-only yang ditetapkan migration
// 2026_09_13_000009 (yang komentarnya sendiri sudah menandai "keputusan
// otorisasi bisnis, bukan hal teknis, kalau Manager tertentu perlu akses
// cukup tambah 1 baris sec_group_permission" -- baris itu sekarang
// ditambahkan atas instruksi eksplisit user).
//
// min_mgmt_level MGMT diturunkan dari 5 (Direksi only) ke 4 (Manager
// Operasional keatas) supaya konsisten dengan login.blade.php yang
// sekarang pakai canAccessModule('MGMT') sbg dasar default landing --
// tanpa turunkan level ini, user level 4 akan diarahkan ke halaman yg
// langsung 403 (EnsureModuleAccess). HANYA grup "Manager Operasional"
// yang ditambahkan permission baru -- Manager fungsional lain (Sales/
// Produksi/Purchasing/Inventory/Finance & Accounting, semua level 3)
// TETAP tidak dapat akses, sesuai threshold "Operational Manager keatas"
// yang diminta (bukan "semua Manager").
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $now = now();

        $sys->table('sec_module')->where('module_code', 'MGMT')->update([
            'min_mgmt_level' => 4,
            'updated_at' => $now,
        ]);

        $moduleId = $sys->table('sec_module')->where('module_code', 'MGMT')->value('id_module');
        $groupId = $sys->table('sec_group')->where('group_name', 'Manager Operasional')->value('id_group');

        if ($moduleId && $groupId) {
            $exists = $sys->table('sec_group_permission')
                ->where('module_id', $moduleId)
                ->where('group_id', $groupId)
                ->exists();

            if (! $exists) {
                $sys->table('sec_group_permission')->insert([
                    'group_id' => $groupId,
                    'module_id' => $moduleId,
                    'can_access' => 1,
                    'can_input' => 0,
                    'can_approve' => 0,
                    'can_export' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => 'migration',
                ]);
            }
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $moduleId = $sys->table('sec_module')->where('module_code', 'MGMT')->value('id_module');
        $groupId = $sys->table('sec_group')->where('group_name', 'Manager Operasional')->value('id_group');

        if ($moduleId && $groupId) {
            $sys->table('sec_group_permission')
                ->where('module_id', $moduleId)
                ->where('group_id', $groupId)
                ->delete();
        }

        $sys->table('sec_module')->where('module_code', 'MGMT')->update([
            'min_mgmt_level' => 5,
            'updated_at' => now(),
        ]);
    }
};
