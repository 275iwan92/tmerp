<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan 2 item menu baru di bawah grup MDATA (Master Data) supaya
// Master Mesin & Master Shift (mockup_master_produksi.html) muncul di mega
// menu, konsisten dengan MD_CUST/MD_UOM yang sudah ada — lihat
// main-menu.blade.php::itemRoutes() untuk pemetaan code -> route Laravel.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $parentId = $sys->table('sys_app_menu')->where('code', 'MDATA')->value('id');

        if (! $parentId) {
            return;
        }

        // `id` bukan AUTO_INCREMENT di skema legacy ini (dump ScriptCase) —
        // isi manual, lanjut dari id tertinggi yang ada.
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        if (! $sys->table('sys_app_menu')->where('code', 'MD_MACHINE')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => $parentId,
                'code' => 'MD_MACHINE',
                'label' => 'Master Mesin',
                'menu_type' => 'item',
                'object_type' => 'form',
                'target_kind' => 'app',
                'sort_order' => 70,
                'is_active' => true,
                'created_at' => now(),
            ]);
        }

        if (! $sys->table('sys_app_menu')->where('code', 'MD_SHIFT')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => $parentId,
                'code' => 'MD_SHIFT',
                'label' => 'Master Shift',
                'menu_type' => 'item',
                'object_type' => 'form',
                'target_kind' => 'app',
                'sort_order' => 80,
                'is_active' => true,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->whereIn('code', ['MD_MACHINE', 'MD_SHIFT'])->delete();
    }
};
