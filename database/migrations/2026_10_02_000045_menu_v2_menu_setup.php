<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Backlog UI-21: Setup > Settings > Menu Setup (Grid Lookup v2 atas sys_app_menu: label, ikon, urutan, aktif).
// Memakai lagi baris SET_MENU ("Susunan Menu" v1, mati sejak 2026-09-07) -- dipindah ke grup Settings dan dihidupkan.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $group = $menu()->where('code', 'SET_GRP_SETTINGS')->value('id');
        if (! $group) {
            return;
        }
        $menu()->where('code', 'SET_MENU')->update(['parent_id' => $group, 'label' => 'Menu Setup', 'target' => 'v2:menu-setup', 'object_type' => 'grid',
            'sort_order' => 46, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_note' => null, 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
    }

    public function down(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $module = $menu()->where('code', 'SET_GRP_SETTINGS')->value('parent_id');
        $menu()->where('code', 'SET_MENU')->where('target', 'v2:menu-setup')->update(['parent_id' => $module, 'label' => 'Susunan Menu', 'target' => 'grid_app_menu.php',
            'sort_order' => 60, 'activate' => 0, 'status_code' => 'NOT_BUILT']);
    }
};
