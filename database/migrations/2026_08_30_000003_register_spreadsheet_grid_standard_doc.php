<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan spreadsheetgridstandard.md (baru, docs/uistandardindex.md) --
// adopsi perilaku AutoFill DataTables.net versi ringan, keputusan user
// 2026-08-30, pilot Master Currency (baru) + list Satuan di Master UOM.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_doc_reference')->where('file_name', 'spreadsheetgridstandard.md')->exists()) {
            return;
        }

        $sys->table('sys_doc_reference')->insert([
            'file_name' => 'spreadsheetgridstandard.md',
            'title' => 'Standar Grid Spreadsheet-Editable (Adopsi AutoFill)',
            'category' => 'STANDARD',
            'related_menu_code' => null,
            'description' => 'Klik sel jadi editable, drag-fill copy nilai ke bawah, "+ Baris" langsung dari tabel, simpan batch. Versi ringan buatan sendiri (Alpine+Livewire) dari perilaku AutoFill DataTables.net -- HANYA master kecil/scalar (Currency, UOM).',
            'sort_order' => 49,
            'activate' => true,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'spreadsheetgridstandard.md')->delete();
    }
};
