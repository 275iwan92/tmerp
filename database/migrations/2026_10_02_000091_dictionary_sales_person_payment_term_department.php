<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English tiga Grid Lookup baru (Sales Person, Payment Terms, Department).
// Nama menu tetap English. Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        'People chosen as PIC Sales in Inquiry, Quotation and Customer Order. Internal = our employee (choose the employee), External = agent or freelance. A saved name cannot be changed because documents refer to it; deactivate it instead.' => 'Orang yang dipilih sebagai PIC Sales di Inquiry, Quotation dan Customer Order. Internal = pegawai kita (pilih pegawainya), External = agen atau freelance. Nama yang sudah tersimpan tidak bisa diubah karena dirujuk dokumen; nonaktifkan saja.',
        'Sales Name' => 'Nama Sales', 'Internal' => 'Internal', 'External' => 'Eksternal',
        'An external sales person cannot be linked to an employee.' => 'Sales eksternal tidak bisa dihubungkan ke pegawai.',
        'Terms chosen in Master Customer, Master Supplier, Quotation and Customer Order. Days = credit days used for the invoice due date; leave it empty when no due date applies. A saved code cannot be changed because documents refer to it; deactivate it instead.' => 'Syarat bayar yang dipilih di Master Customer, Master Supplier, Quotation dan Customer Order. Days = hari kredit untuk jatuh tempo tagihan; kosongkan bila tanpa jatuh tempo. Kode tersimpan tidak bisa diubah karena dirujuk dokumen; nonaktifkan saja.',
        'Term Name' => 'Nama Syarat Bayar', 'Days' => 'Hari', 'Order' => 'Urutan',
        'Departments chosen in Master Position, Purchase Requisition, Shift, Roster and Stock Out. Division is optional (empty = serves all divisions). A saved code cannot be changed because programs and documents refer to it; deactivate it instead.' => 'Departemen yang dipilih di Master Posisi, Purchase Requisition, Shift, Roster dan Stock Out. Divisi boleh kosong (kosong = melayani semua divisi). Kode tersimpan tidak bisa diubah karena dirujuk program dan dokumen; nonaktifkan saja.',
        'Department Name' => 'Nama Departemen', 'Division' => 'Divisi',
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        $db = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void
    {
        // isi kamus dibiarkan (bisa sudah diubah user)
    }
};
