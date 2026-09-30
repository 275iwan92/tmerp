<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Kamus = kunci teks PERSIS: "Unit price" dan "Unit Price" adalah dua teks sumber berbeda. Collation bawaan (unicode_ci)
// menganggapnya sama -> satu terjemahan hilang dan lookup PHP (case-sensitive) meleset. source_en -> utf8mb4_bin,
// lalu isi ulang pasangan label 2026-10-01 yang tadi bentrok (idempoten).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $db->statement("ALTER TABLE sys_dictionary MODIFY source_en VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'teks English (bahasa dasar database), persis'");
        $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', 'Pick a date')->where('created_by', 'migration')->update(['source_en' => 'Choose a date']);

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
        DB::connection('tm2sys')->statement("ALTER TABLE sys_dictionary MODIFY source_en VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    }
};
