<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Item menu "Migrasi Data" — nempel di grup SETUP (bukan mega menu utama),
// sama pola dengan SET_USER/SET_GROUP/SET_PERM: satu halaman Livewire
// pages::security.index, tab dibedakan lewat query ?tab=. Otomatis ke-gate
// middleware 'admin' karena satu route dengan security.index (routes/web.php).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $parentId = $sys->table('sys_app_menu')->where('code', 'SETUP')->value('id');

        if (! $parentId) {
            return;
        }

        if (! $sys->table('sys_app_menu')->where('code', 'SET_MIGRATION')->exists()) {
            $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

            $sys->table('sys_app_menu')->insert([
                'id' => $nextId,
                'parent_id' => $parentId,
                'code' => 'SET_MIGRATION',
                'label' => 'Migrasi Data Legacy',
                'menu_type' => 'item',
                'object_type' => 'form',
                'target_kind' => 'app',
                'sort_order' => 40,
                'is_active' => true,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_MIGRATION')->delete();
    }
};
