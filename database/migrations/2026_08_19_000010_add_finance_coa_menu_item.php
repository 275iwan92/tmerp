<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan leaf menu "Master COA" di bawah grup ACC (Accounting, id=620)
// yang sudah ada di bawah modul FINACC — lihat main-menu.blade.php::itemRoutes()
// untuk pemetaan code -> route Laravel. Sort_order 5 supaya tampil sebelum
// Jurnal (10)/Buku Besar(20) — COA adalah master, dibutuhkan lebih dulu.
// Sekalian grant akses modul FINACC ke DIREKSI (belum ada grup Finance/Costing
// khusus — open item FIN_STATE.yaml), mirror pola grant COSTING di migration
// 2026_08_15_000001 supaya modul baru ini langsung bisa diuji non-admin.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $parentId = $sys->table('sys_app_menu')->where('code', 'ACC')->value('id');

        if ($parentId) {
            $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

            if (! $sys->table('sys_app_menu')->where('code', 'ACC_COA')->exists()) {
                $sys->table('sys_app_menu')->insert([
                    'id' => $nextId,
                    'parent_id' => $parentId,
                    'code' => 'ACC_COA',
                    'label' => 'Master COA',
                    'menu_type' => 'item',
                    'object_type' => 'grid',
                    'target_kind' => 'app',
                    'sort_order' => 5,
                    'is_active' => true,
                    'created_at' => now(),
                ]);
            }
        }

        $direksiId = $sys->table('sec_group')->where('group_code', 'DIREKSI')->value('id_group');
        $finaccId = $sys->table('sec_module')->where('module_code', 'FINACC')->value('id_module');

        if ($direksiId && $finaccId) {
            $sys->table('sec_group_permission')->updateOrInsert(
                ['group_id' => $direksiId, 'module_id' => $finaccId],
                ['can_access' => true, 'can_input' => true, 'can_approve' => true, 'can_export' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'ACC_COA')->delete();

        $direksiId = $sys->table('sec_group')->where('group_code', 'DIREKSI')->value('id_group');
        $finaccId = $sys->table('sec_module')->where('module_code', 'FINACC')->value('id_module');

        if ($direksiId && $finaccId) {
            $sys->table('sec_group_permission')->where('group_id', $direksiId)->where('module_id', $finaccId)->delete();
        }
    }
};
