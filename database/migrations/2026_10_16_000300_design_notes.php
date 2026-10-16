<?php

/*
 * REMARK
 * Tujuan       : Bagian Design Notes: catatan perancangan pengembang (rencana teknis, fase, keputusan) dipisahkan dari Guides sebagai kilas balik untuk Jo: mengapa sesuatu dirancang demikian.
 * Sumber hulu  : docs/design-notes/<area>/NN_slug.md lewat php artisan guides:import (diubah dari 50 topik Help yang dibuang 2026-10-16).
 * Input        : -
 * Output       : Kolom sys_guide.section ('guide' | 'design'); satu baris sys_app_menu SET_DESIGN_NOTES di grup Help and Support; pasangan kamus.
 * Dipakai oleh : GuidesImport, GuidesLookup, DesignNotesLookup.
 * Status       : Jalankan di ddt* dan tm2* (aturan paritas Jo 2026-10-16).
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE = 'SET_DESIGN_NOTES';

    private const UI = [
        'Design Notes' => 'Catatan Perancangan',
        'Design notes: why each module was designed the way it is (plans, phases, decisions). A look back for the owner; nothing can be changed here.' => 'Catatan perancangan: mengapa tiap modul dirancang demikian (rencana, fase, keputusan). Kilas balik untuk pemilik; tidak ada yang bisa diubah di sini.',
    ];

    public function up(): void
    {
        $schema = Schema::connection('tm2sys');
        if (! $schema->hasColumn('sys_guide', 'section')) {
            $schema->table('sys_guide', fn (Blueprint $t) => $t->string('section', 10)->default('guide')->after('id_guide')->index());
        }

        $db = DB::connection('tm2sys');
        $menu = $db->table('sys_app_menu');
        $help = (clone $menu)->where('code', 'SET_GRP_HELP')->first();
        if ($help && ! (clone $menu)->where('code', self::CODE)->exists()) {
            $menu->insert(['parent_id' => $help->id, 'code' => self::CODE, 'label' => 'Design Notes', 'icon' => 'fa-solid fa-clock-rotate-left',
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:setup-design-notes', 'sort_order' => 6, 'activate' => 1,
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
        Schema::connection('tm2sys')->table('sys_guide', fn (Blueprint $t) => $t->dropColumn('section'));
    }
};
