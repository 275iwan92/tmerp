<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Label layar v2 & DevLib yang dulu ditulis Indonesia dipindah ke English (LOCKED user 2026-10-01: DB & kode English,
// bahasa lain lewat kamus). Terjemahan Indonesianya (teks lama persis) diisi ke kamus supaya tampilan ID TIDAK berubah.
// Sumber pasangan: database/data/dictionary_ui_2026_10_01.json (Indonesia => English).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $now = now();
        foreach (json_decode(file_get_contents(database_path('data/dictionary_ui_2026_10_01.json')), true) as $id => $en) {
            $row = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->first();
            $key = $row?->id_dictionary ?? $db->table('sys_dictionary')->insertGetId(['context' => 'ui', 'source_en' => $en, 'created_at' => $now, 'created_by' => 'migration']);
            if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->exists()) {
                $db->table('sys_dictionary_lang')->insert(['id_dictionary' => $key, 'lang_code' => 'id', 'text' => $id, 'created_at' => $now, 'created_by' => 'migration']);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_dictionary')->where('context', 'ui')->where('created_by', 'migration')->delete();
    }
};
