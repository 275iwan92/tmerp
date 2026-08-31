<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan themestandard.md (baru, docs/uistandardindex.md) -- matrix tema
// 4 mode (pastel/cerah/dark/hitam) x 3 corak (biru/hijau/merah), instruksi
// user 2026-08-31, data 100% di tm2sys.sys_theme.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_doc_reference')->where('file_name', 'themestandard.md')->exists()) {
            return;
        }

        $sys->table('sys_doc_reference')->insert([
            'file_name' => 'themestandard.md',
            'title' => 'Standar Tema (Light/Dark/Pastel/Cerah x Corak Warna)',
            'category' => 'STANDARD',
            'related_menu_code' => null,
            'description' => 'Matrix 4 mode x 3 corak tersimpan di tm2sys.sys_theme (family/hue), dipilih user lewat grid picker di dropdown avatar. Cakupan: shell lama saja, belum halaman iframe/Filament admin.',
            'sort_order' => 50,
            'activate' => true,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'themestandard.md')->delete();
    }
};
