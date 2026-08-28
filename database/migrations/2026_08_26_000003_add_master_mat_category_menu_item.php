<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan menu Master Kategori Bahan Baku (mat_category, pengganti
// doc_rmprefix1..5 -- migration 2026_08_26_000002) di grup MDATA, tepat
// setelah Master Bahan Baku (MD_RAW) karena keduanya berkaitan erat.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $parentId = $sys->table('sys_app_menu')->where('code', 'MDATA')->value('id');

        if (! $parentId) {
            return;
        }

        if ($sys->table('sys_app_menu')->where('code', 'MD_MATCAT')->exists()) {
            return;
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $parentId,
            'code' => 'MD_MATCAT',
            'label' => 'Master Kategori Bahan Baku',
            'menu_type' => 'item',
            'object_type' => 'form',
            'target_kind' => 'app',
            'sort_order' => 45,
            'is_active' => true,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MD_MATCAT')->delete();
    }
};
