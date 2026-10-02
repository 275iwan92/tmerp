<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Kamus setelah sapuan multi-bahasa (2026-10-03):
// 1. terjemahan Indonesia untuk teks English yang sudah tercatat tetapi masih kosong (label menu, judul grid, istilah
//    cetakan, teks DevLib) -- database/data/dictionary_fill_2026_10_03.json, hanya mengisi yang kosong;
// 2. baris basi dihapus -- database/data/dictionary_stale_2026_10_03.json: teks Indonesia / nilai data yang dulu
//    tercatat sebagai "sumber English" (label lama yang kini English di kode, nama aplikasi, contoh isian). Hanya
//    baris yang belum punya terjemahan apa pun yang dihapus.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        // pasangan yang ditambahkan setelah 000303 (label layar Data Dictionary, model, route kecil)
        (require __DIR__.'/2026_10_02_000300_dictionary_language_sweep.php')->up();

        $db = DB::connection('tm2sys');
        $fill = json_decode(file_get_contents(database_path('data/dictionary_fill_2026_10_03.json')), true) ?: [];
        foreach ($fill as $en => $id) {
            foreach ($db->table('sys_dictionary')->where('source_en', $en)->pluck('id_dictionary') as $key) {
                if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                    $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
                }
            }
        }
        $stale = json_decode(file_get_contents(database_path('data/dictionary_stale_2026_10_03.json')), true) ?: [];
        foreach (array_chunk($stale, 100) as $chunk) {
            $ids = $db->table('sys_dictionary')->where('context', 'ui')->whereIn('source_en', $chunk)
                ->whereNotExists(fn ($q) => $q->from('sys_dictionary_lang as l')->whereColumn('l.id_dictionary', 'sys_dictionary.id_dictionary')->where('l.text', '<>', ''))
                ->pluck('id_dictionary');
            $db->table('sys_dictionary_lang')->whereIn('id_dictionary', $ids)->delete();
            $db->table('sys_dictionary')->whereIn('id_dictionary', $ids)->delete();
        }
        Dictionary::flush();
    }

    public function down(): void
    {
        // isi kamus dibiarkan
    }
};
