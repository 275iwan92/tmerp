<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Sapuan multi-bahasa 2026-10-02: teks layar v2, pesan flash / error, objek mockup-parts dan service yang dulu ditulis
// Indonesia kini English di kode. Terjemahan Indonesianya (teks lama persis) ada di
// database/data/dictionary_ui_2026_10_02_sweep.json (English => Indonesia) supaya tampilan Indonesia tidak berubah.
// Hanya mengisi terjemahan yang masih kosong (isi yang sudah diubah user tidak ditimpa).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        $db = DB::connection('tm2sys');
        $pairs = json_decode(file_get_contents(database_path('data/dictionary_ui_2026_10_02_sweep.json')), true) ?: [];
        foreach ($pairs as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
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
