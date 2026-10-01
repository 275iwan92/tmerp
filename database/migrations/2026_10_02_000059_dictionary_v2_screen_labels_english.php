<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Label layar v2 (Quotation, PR, PO, SPB, CO, Inquiry, Master Card, Approval Setting, ...) yang masih Indonesia dan lolos penjaga lama
// kini English di kode; terjemahan Indonesianya di sini. Label tombol tidak ikut (tombol selalu English). Hanya mengisi yang kosong.
return new class extends Migration
{
    private const UI = [
        'Applies when value ≥' => 'Berlaku bila nilai ≥',
        'Attn (contact)' => 'Attn (kontak)',
        'Cancel revision' => 'Cancel revisi',
        'Contact' => 'Kontak',
        'Contact (attn)' => 'Kontak (attn)',
        'Department' => 'Departemen',
        'Item Source' => 'Sumber Item',
        'Knife / UP' => 'Pisau / UP',
        'Nearest Due Date' => 'Due date terdekat',
        'PO Type' => 'Tipe PO',
        'Parent (when derived from a revision)' => 'Induk (bila turunan revisi)',
        'Procurement Type' => 'Tipe Pengadaan',
        'Product / Service' => 'Produk / Jasa',
        'Product Type' => 'Tipe Produk',
        'Product Type (dieline)' => 'Tipe Produk (dieline)',
        'Product Type (international dieline)' => 'Tipe Produk (dieline internasional)',
        'Rack Location' => 'Lokasi Rak',
        'Result' => 'Hasil',
        'Revision reason' => 'Alasan revisi',
        'Sales PIC · Payment Term' => 'Sales PIC · Termin',
        'Shipped' => 'Terkirim',
        'Source' => 'Sumber',
        'Tolerance Override Reason' => 'Alasan Override Toleransi',
        'Type' => 'Tipe',
        'Up to value (empty = no limit)' => 's.d. nilai (kosong = tanpa batas)',
        'Valid Until' => 'Berlaku s/d',
        'Validity' => 'Masa berlaku',
        'product dimensions & live dieline preview' => 'dimensi produk & preview dieline live',
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
