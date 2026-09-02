<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Tambah slot menu "Aset Tetap" di grup ACC (Accounting, FA) -- permintaan
// user 2026-09-02 "masukkan menu fixed asset ke dalam FA". Belum ada tabel/
// layar Aset Tetap sama sekali di codebase (dicek: cuma akun COA "ASET
// TETAP PABRIK" di fin_account, bukan modul register+penyusutan) -- jadi
// menu ini SENGAJA NOT_BUILT dulu (placeholder slot, pola sama ACC_JRN/
// ACC_GL/ACC_BS/ACC_PL), layar+skema register aset (kode aset, tgl
// perolehan, nilai perolehan, metode penyusutan, umur ekonomis, akun aset/
// akumulasi penyusutan) perlu dirancang terpisah -- bukan keputusan yang
// bisa ditebak diam-diam.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_app_menu')->where('code', 'ACC_FIXEDASSET')->exists()) {
            return;
        }

        $parentId = $sys->table('sys_app_menu')->where('code', 'ACC')->value('id');
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $parentId,
            'code' => 'ACC_FIXEDASSET',
            'label' => 'Aset Tetap',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target_kind' => 'app',
            'sort_order' => 7,
            'activate' => true,
            'status_code' => 'NOT_BUILT',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'ACC_FIXEDASSET')->delete();
    }
};
