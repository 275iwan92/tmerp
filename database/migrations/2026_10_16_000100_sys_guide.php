<?php

/*
 * REMARK
 * Tujuan       : Tabel dan menu Guides: panduan bergaya artikel blog per modul (cara kerja layar, engine, formula) untuk dibaca dan direview Jo.
 * Sumber hulu  : Berkas docs/guides/<modul>/NN_slug.md lewat php artisan guides:import (berkas = sumber tulisan, tabel = bahan tampil dan pencarian).
 * Input        : -
 * Output       : tm2sys.sys_guide (module, slug unik, title, summary, body umum, body_secret = bagian rumus, FULLTEXT title+summary+body); satu baris sys_app_menu SET_GUIDES di grup Help and Support; pasangan kamus.
 * Dipakai oleh : App\Console\Commands\GuidesImport, App\Support\V2\Grid\Setup\GuidesLookup.
 * Status       : Jalankan di ddt* (--env=mockup) dan tm2* (aturan paritas Jo 2026-10-16).
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE = 'SET_GUIDES';

    private const UI = [
        'Guides' => 'Panduan',
        'Guides, read like articles: how each screen and engine works. Search inside the text, then open one to read it in full. Nothing can be changed here.' => 'Panduan bergaya artikel: cara kerja tiap layar dan engine. Cari di dalam teks, lalu buka satu untuk dibaca penuh. Tidak ada yang bisa diubah di sini.',
        'Formulas (owner only)' => 'Rumus (hanya pemilik)',
    ];

    public function up(): void
    {
        $schema = Schema::connection('tm2sys');

        if (! $schema->hasTable('sys_guide')) {
            $schema->create('sys_guide', function (Blueprint $t) {
                $t->id('id_guide');
                $t->string('module', 30);
                $t->string('slug', 120)->unique();
                $t->string('title', 200);
                $t->string('summary', 600)->nullable();
                $t->longText('body');                    // bagian umum, boleh dibaca semua yang berhak membuka Guides
                $t->longText('body_secret')->nullable(); // bagian rumus: hanya yang lolos FormulaAccess
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->string('source_path', 255);
                $t->unsignedInteger('line_count')->default(0);
                $t->timestamps();
                $t->fullText(['title', 'summary', 'body']);
            });
        }

        $db = DB::connection('tm2sys');
        $menu = $db->table('sys_app_menu');
        $help = (clone $menu)->where('code', 'SET_GRP_HELP')->first();
        if ($help && ! (clone $menu)->where('code', self::CODE)->exists()) {
            $menu->insert(['parent_id' => $help->id, 'code' => self::CODE, 'label' => 'Guides', 'icon' => 'fa-solid fa-book-open-reader',
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:setup-guides', 'sort_order' => 5, 'activate' => 1,
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
        Schema::connection('tm2sys')->dropIfExists('sys_guide');
    }
};
