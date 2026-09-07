<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User 2026-09-07: sidebar "Settings"+"Security" (main-menu.blade.php) masih
// HARDCODE 2 array Alpine literal (label/icon/urutan tulis manual di Blade) --
// beda dari semua modul lain yang 100% data-driven dari sys_app_menu lewat
// menuTree()/sidebarGroups. Migration ini bikin sumber kebenaran tunggal:
// tambah 2 baris menu_type='group' (SET_GRP_SETTINGS/SET_GRP_SECURITY,
// mirror pola PROC_GRP_* dsb) di bawah SETUP (parent_id=9), lalu reparent
// 9 leaf item yang sudah ada supaya jadi anak grup masing2 -- persis
// struktur module+group+item yang dipakai modul lain, TIDAK ada leaf
// langsung di bawah module lagi (kecuali SET_THEME/SET_MENU, sengaja
// dibiarkan -- 2 fitur dead/NOT_BUILT di luar scope Settings/Security saat
// ini, tidak pernah dirender di mana pun).
//
// Label SET_USER/SET_GROUP/SET_PERM/SET_MIGRATION diselaraskan ke teks
// Inggris yang SUDAH tampil ke user (dulu override manual di array Alpine,
// beda dari sys_app_menu.label lama yang Indonesia -- sekarang cuma 1
// sumber). Icon per-leaf (dulu juga cuma ada di array Alpine) dipindah ke
// kolom `icon` supaya ikonnya tetap sama persis, bukan fallback generik
// iconFor().
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $setupId = $sys->table('sys_app_menu')->where('code', 'SETUP')->value('id');

        $settingsGroupId = $sys->table('sys_app_menu')->where('code', 'SET_GRP_SETTINGS')->value('id');
        if (! $settingsGroupId) {
            $settingsGroupId = (int) $sys->table('sys_app_menu')->max('id') + 1;
            $sys->table('sys_app_menu')->insert([
                'id' => $settingsGroupId,
                'parent_id' => $setupId,
                'code' => 'SET_GRP_SETTINGS',
                'label' => 'Settings',
                'menu_type' => 'group',
                'target_kind' => 'app',
                'sort_order' => 100,
                'activate' => 1,
                'status_code' => 'NOT_BUILT',
                'created_at' => now(),
            ]);
        }

        $securityGroupId = $sys->table('sys_app_menu')->where('code', 'SET_GRP_SECURITY')->value('id');
        if (! $securityGroupId) {
            $securityGroupId = (int) $sys->table('sys_app_menu')->max('id') + 1;
            $sys->table('sys_app_menu')->insert([
                'id' => $securityGroupId,
                'parent_id' => $setupId,
                'code' => 'SET_GRP_SECURITY',
                'label' => 'Security',
                'menu_type' => 'group',
                'target_kind' => 'app',
                'sort_order' => 200,
                'activate' => 1,
                'status_code' => 'NOT_BUILT',
                'created_at' => now(),
            ]);
        }

        $settings = [
            'SET_CONFIG' => ['icon' => 'fa-solid fa-id-badge', 'sort_order' => 10],
            'SET_LANG' => ['icon' => 'fa-solid fa-language', 'sort_order' => 20],
            'SET_LOCALIZATION' => ['icon' => 'fa-solid fa-globe', 'sort_order' => 30],
            'SET_CONSTANTS' => ['icon' => 'fa-solid fa-sliders', 'sort_order' => 40],
        ];
        foreach ($settings as $code => $attrs) {
            $sys->table('sys_app_menu')->where('code', $code)->update([
                'parent_id' => $settingsGroupId,
                'icon' => $attrs['icon'],
                'sort_order' => $attrs['sort_order'],
            ]);
        }

        $security = [
            'SET_USER' => ['label' => 'Account', 'icon' => 'fa-solid fa-user-gear', 'sort_order' => 10],
            'SET_GROUP' => ['label' => 'Group', 'icon' => 'fa-solid fa-people-group', 'sort_order' => 20],
            'SET_PERM' => ['label' => 'Group per Submodule', 'icon' => 'fa-solid fa-diagram-project', 'sort_order' => 30],
            'SET_MIGRATION' => ['label' => 'Migration', 'icon' => 'fa-solid fa-database', 'sort_order' => 40],
            'SET_TICKETS' => ['label' => 'Support Tickets', 'icon' => 'fa-solid fa-ticket', 'sort_order' => 50],
        ];
        foreach ($security as $code => $attrs) {
            $sys->table('sys_app_menu')->where('code', $code)->update([
                'parent_id' => $securityGroupId,
                'label' => $attrs['label'],
                'icon' => $attrs['icon'],
                'sort_order' => $attrs['sort_order'],
            ]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $setupId = $sys->table('sys_app_menu')->where('code', 'SETUP')->value('id');

        foreach (['SET_CONFIG', 'SET_LANG', 'SET_LOCALIZATION', 'SET_CONSTANTS', 'SET_USER', 'SET_GROUP', 'SET_PERM', 'SET_MIGRATION', 'SET_TICKETS'] as $code) {
            $sys->table('sys_app_menu')->where('code', $code)->update(['parent_id' => $setupId, 'icon' => null]);
        }

        $sys->table('sys_app_menu')->where('code', 'SET_USER')->update(['label' => 'Pengguna']);
        $sys->table('sys_app_menu')->where('code', 'SET_GROUP')->update(['label' => 'Grup']);
        $sys->table('sys_app_menu')->where('code', 'SET_PERM')->update(['label' => 'Hak Akses Grup']);
        $sys->table('sys_app_menu')->where('code', 'SET_MIGRATION')->update(['label' => 'Migrasi Data Legacy']);

        $sys->table('sys_app_menu')->whereIn('code', ['SET_GRP_SETTINGS', 'SET_GRP_SECURITY'])->delete();
    }
};
