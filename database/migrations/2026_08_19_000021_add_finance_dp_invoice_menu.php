<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Leaf menu "Uang Muka (DP)" di bawah grup FIN (Finance, id=610) yang sudah
// ada di bawah modul FINACC — antara Kasir(10) & Piutang(20), lihat
// main-menu.blade.php::itemRoutes().
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $finGroupId = $sys->table('sys_app_menu')->where('code', 'FIN')->value('id');

        if (! $finGroupId) {
            return;
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        if (! $sys->table('sys_app_menu')->where('code', 'FIN_DP')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId,
                'parent_id' => $finGroupId,
                'code' => 'FIN_DP',
                'label' => 'Uang Muka (DP)',
                'menu_type' => 'item',
                'object_type' => 'form',
                'target_kind' => 'app',
                'sort_order' => 15,
                'is_active' => true,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'FIN_DP')->delete();
    }
};
