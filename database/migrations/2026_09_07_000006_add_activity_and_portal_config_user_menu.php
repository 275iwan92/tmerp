<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User 2026-09-07: "Aktivitas saya" & "User Configuration" di dalam grup
// collapsible "Configuration" (dropdown avatar) masih hardcode di
// main-menu.blade.php -- padahal strukturnya identik dgn baris lain yang
// SUDAH data-driven dari sys_user_menu (migration
// 2026_09_03_000004_make_avatar_dropdown_data_driven.php, section
// "Pesan & Info"/"Akun"). Tambah section BARU "Configuration" utk 2 baris
// ini -- dibaca via computed configMenuItems() (main-menu.blade.php), TIDAK
// ikut whereIn('section', ['Pesan & Info','Akun']) punya userMenuItems()
// supaya tetap dirender nested di dalam grup collapse, bukan ikut nongol di
// list flat atas. Widget Tema (swatch grid) & Bahasa (flag picker) TETAP
// hardcode -- bukan baris label+klik generik, App\Models\Sys\SysUserMenu
// docblock sudah menjelaskan alasan yang sama sejak awal.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_user_menu')->insert([
            ['section' => 'Configuration', 'code' => 'UM_PORTALCFG', 'label' => 'User Configuration', 'icon' => 'fa-solid fa-id-card', 'action_type' => 'modal', 'action_target' => 'portalOpen', 'badge_source' => null, 'visibility' => 'employee_linked', 'sort_order' => 10, 'activate' => 1],
            ['section' => 'Configuration', 'code' => 'UM_ACTIVITY', 'label' => 'Aktivitas saya', 'icon' => 'fa-solid fa-clock-rotate-left', 'action_type' => 'menu', 'action_target' => 'SET_USER', 'badge_source' => null, 'visibility' => 'all', 'sort_order' => 20, 'activate' => 1],
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_user_menu')->whereIn('code', ['UM_PORTALCFG', 'UM_ACTIVITY'])->delete();
    }
};
