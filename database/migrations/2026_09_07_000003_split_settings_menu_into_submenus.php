<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-09-07: tombol sidebar "Settings" (dulu 1 layar
// gado-gado -- Identitas Aplikasi + Bahasa+Terjemahan + Semua Konstanta
// digabung 1 route setup.configuration) dipecah jadi 4 submenu/layar
// terpisah: Identifikasi, Language, Localization, Constants -- mirror pola
// grup "Security" (main-menu.blade.php). SET_CONFIG & SET_LANG SUDAH ADA di
// sys_app_menu (dari migration add_configuration_menu_item &
// consolidate_setup_security lama) -- direname labelnya + diarahkan ulang ke
// route baru lewat itemRoutes(). SET_LOCALIZATION & SET_CONSTANTS BARU.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'SET_CONFIG')->update([
            'label' => 'Identifikasi',
            'doc_slug' => 'configurationstandard',
        ]);

        // Dulu NOT_BUILT (dead entry, tak pernah punya route nyata di
        // itemRoutes() -- lihat CLAUDE.md konvensi status_code). Sekarang
        // benar2 dibangun sbg route setup.language.
        $sys->table('sys_app_menu')->where('code', 'SET_LANG')->update([
            'label' => 'Language',
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'doc_slug' => 'configurationstandard',
        ]);

        $setupId = $sys->table('sys_app_menu')->where('code', 'SETUP')->value('id');
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        if (! $sys->table('sys_app_menu')->where('code', 'SET_LOCALIZATION')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId,
                'parent_id' => $setupId,
                'code' => 'SET_LOCALIZATION',
                'label' => 'Localization',
                'menu_type' => 'item',
                'object_type' => 'form',
                'target_kind' => 'app',
                'sort_order' => 41,
                'activate' => 1,
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
                'doc_slug' => 'configurationstandard',
                'created_at' => now(),
            ]);
            $nextId++;
        }

        if (! $sys->table('sys_app_menu')->where('code', 'SET_CONSTANTS')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId,
                'parent_id' => $setupId,
                'code' => 'SET_CONSTANTS',
                'label' => 'Constants',
                'menu_type' => 'item',
                'object_type' => 'form',
                'target_kind' => 'app',
                'sort_order' => 61,
                'activate' => 1,
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
                'doc_slug' => 'configurationstandard',
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'SET_CONFIG')->update(['label' => 'Settings']);
        $sys->table('sys_app_menu')->where('code', 'SET_LANG')->update(['label' => 'Bahasa', 'status_code' => 'NOT_BUILT']);
        $sys->table('sys_app_menu')->where('code', 'SET_LOCALIZATION')->delete();
        $sys->table('sys_app_menu')->where('code', 'SET_CONSTANTS')->delete();
    }
};
