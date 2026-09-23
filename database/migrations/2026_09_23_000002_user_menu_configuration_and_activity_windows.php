<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu profil (permintaan user 2026-09-23): "Configuration" = jendela popup
// pengaturan milik tiap user (Themes/Language/...), untuk SEMUA user.
// Grup lipat "Configuration" di dropdown dihapus; isinya dipindah:
//  - UM_CONFIG (baru)  -> jendela configWinOpen
//  - UM_ACTIVITY       -> jendela activityWinOpen (dulu openTab SET_USER = layar
//                         admin, mati utk non-admin & salah tujuan utk admin)
//  - UM_PORTALCFG      -> tetap modal lamanya, pindah ke section Akun
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_user_menu')->updateOrInsert(
            ['code' => 'UM_CONFIG'],
            [
                'section' => 'Akun', 'label' => 'Configuration', 'icon' => 'fa-solid fa-sliders',
                'action_type' => 'modal', 'action_target' => 'configWinOpen', 'badge_source' => null,
                'is_danger' => 0, 'admin_only' => 0, 'visibility' => 'all', 'sort_order' => 85, 'activate' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]
        );

        $db->table('sys_user_menu')->where('code', 'UM_ACTIVITY')->update([
            'section' => 'Akun', 'action_type' => 'modal', 'action_target' => 'activityWinOpen', 'sort_order' => 75, 'updated_at' => now(),
        ]);

        $db->table('sys_user_menu')->where('code', 'UM_PORTALCFG')->update([
            'section' => 'Akun', 'sort_order' => 45, 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_user_menu')->where('code', 'UM_CONFIG')->delete();
        $db->table('sys_user_menu')->where('code', 'UM_ACTIVITY')->update([
            'section' => 'Configuration', 'action_type' => 'menu', 'action_target' => 'SET_USER', 'sort_order' => 20,
        ]);
        $db->table('sys_user_menu')->where('code', 'UM_PORTALCFG')->update(['section' => 'Configuration', 'sort_order' => 10]);
    }
};
