<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-20 rollout (backlog01): Setup -> grid v2. Constants (Grid Lookup), Account dan Group (Grid Master, edit = form v1),
// plus baris menu baru Constant Categories (Grid Lookup; dulu kategori konstanta hanya bisa ditambah lewat migration, UI-18).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // kode => [slug v2, target lama]
    private const MAP = [
        'SET_CONSTANTS' => ['setup-constants', null],
        'SET_USER' => ['security-accounts', 'security.php?tab=users'],
        'SET_GROUP' => ['security-groups', 'security.php?tab=groups'],
    ];

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        foreach (self::MAP as $code => [$slug]) {
            $menu()->where('code', $code)->where(fn ($q) => $q->whereNull('target')->orWhere('target', 'not like', 'v2:%'))
                ->update(['target' => 'v2:'.$slug, 'object_type' => 'grid', 'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }

        $constants = $menu()->where('code', 'SET_CONSTANTS')->first();
        if ($constants && ! $menu()->where('code', 'SET_CONSTANT_CAT')->exists()) {
            $menu()->insert([
                'parent_id' => $constants->parent_id, 'code' => 'SET_CONSTANT_CAT', 'label' => 'Constant Categories', 'icon' => 'fa-solid fa-folder-tree',
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:setup-constant-categories', 'sort_order' => $constants->sort_order + 1,
                'activate' => 1, 'required_object' => $constants->required_object, 'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        foreach (self::MAP as $code => [$slug, $old]) {
            $menu()->where('code', $code)->where('target', 'v2:'.$slug)->update(['target' => $old, 'object_type' => 'form']);
        }
        $menu()->where('code', 'SET_CONSTANT_CAT')->delete();
    }
};
