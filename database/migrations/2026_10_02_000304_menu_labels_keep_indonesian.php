<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Label menu yang diganti English (000302) harus tetap tampil dengan teks Indonesia lamanya. Beberapa kuncinya sudah
// termuat lebih dulu oleh sapuan kamus dengan terjemahan = teks English itu sendiri (judul grid yang memang English),
// sehingga menu berbahasa Indonesia ikut menjadi English. Di sini terjemahan identik itu diganti teks menu lama.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        $pairs = (new ReflectionClassConstant(require __DIR__.'/2026_10_02_000302_menu_labels_english.php', 'ACTIVE'))->getValue();
        $db = DB::connection('tm2sys');
        foreach ($pairs as $en => $id) {
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key) {
                $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->whereRaw('BINARY text = ?', [$en])->update(['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void {}
};
