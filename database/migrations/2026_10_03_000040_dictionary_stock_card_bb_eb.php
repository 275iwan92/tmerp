<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk panduan Stock Card susunan baru (user 2026-10-03: kepala = nama item lalu periode; rinci = Date,
// Location, BB, In, Out, Adj, EB mengikuti tabel kartu stok). BB, In, Out, Adj, EB = istilah tetap, tidak masuk kamus.
// Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        'Stock card per item for the period. Each row is one movement at one location: BB = balance before it, In, Out, Adj, EB = balance after it. The Total row covers all locations of the item. For one item, type its code or name in Item and click Show.' => 'Kartu stok per item untuk periode itu. Tiap baris = satu pergerakan di satu lokasi: BB = saldo sebelumnya, In, Out, Adj, EB = saldo sesudahnya. Baris Total mencakup semua lokasi item. Untuk satu item, ketik kode atau namanya di Item lalu klik Show.',
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
