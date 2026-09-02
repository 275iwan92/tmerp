<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftar code menu SET_CONFIG (docs/configurationstandard.md) supaya
// openTab('SET_CONFIG') di main-menu.blade.php bisa resolve (findItem() cari
// dari flatItems() yang dibangun dari sys_app_menu -- lihat pola SET_TICKETS).
// parent_id=9 = modul SETUP (tersembunyi dari topbar, tetap harus terdaftar
// di sys_app_menu supaya code-nya dikenal shell).
return new class extends Migration
{
    public function up(): void
    {
        // id BUKAN auto-increment di tabel ini (dicek 2026-09-02) -- hitung manual.
        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        DB::connection('tm2sys')->table('sys_app_menu')->updateOrInsert(
            ['code' => 'SET_CONFIG'],
            [
                'id' => $nextId,
                'parent_id' => 9,
                'label' => 'Configuration',
                'menu_type' => 'item',
                'object_type' => 'form',
                'target_kind' => 'app',
                'sort_order' => 60,
                'activate' => 1,
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
                'doc_slug' => 'configurationstandard',
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_CONFIG')->delete();
    }
};
