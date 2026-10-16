<?php

/*
 * REMARK
 * Tujuan       : Menambah satu item menu Setup > Help and Support > Technical Guide (SET_TECH_GUIDE) dan pasangan kamus; isi dari sys_guide section 'tg'.
 * Sumber hulu  : docs/tg lewat php artisan guides:import; layar TechGuideLookup.
 * Input        : -
 * Output       : Satu baris sys_app_menu di grup SET_GRP_HELP; dua baris sys_dictionary (ui) dengan terjemahan Indonesia.
 * Dipakai oleh : TechGuideLookup (v2:setup-tech-guide).
 * Status       : Jalankan di ddt* dan tm2* (aturan paritas Jo 2026-10-16); hanya berkas ini: php artisan migrate --path=database/migrations/2026_10_16_000800_tech_guide_menu.php.
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CODE = 'SET_TECH_GUIDE';

    private const UI = [
        'Technical Guide' => 'Panduan Teknis',
        'Technical Guide: how each module works today (concept cards, engine cards, relations), written for agents and the owner. Nothing can be changed here.' => 'Panduan Teknis: cara kerja tiap modul saat ini (kartu konsep, kartu engine, relasi), ditulis untuk agen dan pemilik. Tidak ada yang bisa diubah di sini.',
    ];

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $menu = $db->table('sys_app_menu');
        $help = (clone $menu)->where('code', 'SET_GRP_HELP')->first();
        if ($help && ! (clone $menu)->where('code', self::CODE)->exists()) {
            $menu->insert(['parent_id' => $help->id, 'code' => self::CODE, 'label' => 'Technical Guide', 'icon' => 'fa-solid fa-diagram-project',
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:setup-tech-guide', 'sort_order' => 7, 'activate' => 1,
                'required_object' => null, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(),
                'created_at' => now()]);
        }

        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'group_code' => 'general', 'origin' => 'app',
                'activate' => 1, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', self::CODE)->delete();
    }
};
