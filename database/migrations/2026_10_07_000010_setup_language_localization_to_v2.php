<?php

use App\Support\Localization\AuthText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C1 (docs/menu_tm2_belum_v2.md): Setup > Language dan Setup > Localization v1 dipensiunkan.
//  - Language digantikan Languages (v2:languages, saklar Active).
//  - Teks halaman masuk dipindah dari kamus kunci v1 (sys_translations) ke kamus utama (sys_dictionary, App\Support\Localization\AuthText);
//    terjemahan Indonesia yang sudah ada disalin bila belum ada di kamus. Sisa kunci v1 (kunci layar v1) tetap di sys_translations
//    dan ikut dipensiunkan bersama layar v1-nya.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        foreach (AuthText::TEXTS as $key => $en) {
            $id = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary')
                ?? $db->table('sys_dictionary')->insertGetId(['context' => 'ui', 'source_en' => $en, 'group_code' => 'general', 'origin' => 'app',
                    'activate' => 1, 'created_at' => now(), 'created_by' => 'system']);
            $idText = $db->table('sys_translations')->where('lang_code', 'id')->where('trans_key', $key)->value('trans_value');
            if ($idText && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $id)->where('lang_code', 'id')->exists()) {
                $db->table('sys_dictionary_lang')->insert(['id_dictionary' => $id, 'lang_code' => 'id', 'text' => $idText, 'created_at' => now(), 'created_by' => 'system']);
            }
        }
        $db->table('sys_app_menu')->whereIn('code', ['SET_LANG', 'SET_LOCALIZATION'])->update(['activate' => 0,
            'status_note' => 'Diganti Languages / Translation Dictionary (v2)', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['SET_LANG', 'SET_LOCALIZATION'])->update(['activate' => 1]);
    }
};
