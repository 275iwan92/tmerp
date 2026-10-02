<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Help per layar (user 2026-10-02: "guide cara pakai input dan report sendiri, penjelasan tiap inputan sendiri, penjelasan
// untuk apa report juga sendiri; semua masuk help, supaya AI lokal mudah mencari").
//  - sys_help_topic = satu layar / laporan / panduan umum: untuk apa (purpose) + kata cari.
//  - sys_help_line  = potongan pendek: STEP (cara pakai, berurutan) atau FIELD (penjelasan satu isian / kolom).
// Tiap potongan paling panjang 255 karakter = satu teks kamus: database English, terjemahan lewat sys_dictionary konteks 'help'
// (aturan B.3), dan potongan sependek itu juga yang paling mudah dicari asisten AI lokal.
// Isi awal: resources/data/help/*.json lewat `php artisan help:import`; sesudah itu dirawat di Setup > Help Topics / Help Lines.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        $schema = Schema::connection('tm2sys');

        if (! $schema->hasTable('sys_help_topic')) {
            $schema->create('sys_help_topic', function (Blueprint $t) {
                $t->id('id_help_topic');
                $t->string('topic_key', 80)->unique()->comment('v2 screen slug (sys_app_menu.target without v2:) or guide-<name>');
                $t->string('topic_type', 10)->comment('INPUT | REPORT | GUIDE');
                $t->string('module_code', 30)->nullable()->comment('sec_module.module_code that owns the screen');
                $t->string('title', 150);
                $t->string('purpose', 255)->comment('What the screen or report is for');
                $t->string('keywords', 255)->nullable()->comment('Search words, English and Indonesian, comma separated');
                $t->integer('sort_order')->default(0);
                $t->boolean('activate')->default(true);
                $t->timestamps();
                $t->string('created_by', 100)->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->comment('Help: one row per screen, report or general guide');
            });
        }
        if (! $schema->hasTable('sys_help_line')) {
            $schema->create('sys_help_line', function (Blueprint $t) {
                $t->id('id_help_line');
                $t->unsignedBigInteger('help_topic_id');
                $t->string('line_type', 10)->comment('STEP = how to use, in order | FIELD = one input field or report column explained');
                $t->string('field_label', 100)->nullable()->comment('FIELD: label as written on the screen');
                $t->string('body', 255);
                $t->integer('sort_order')->default(0);
                $t->boolean('activate')->default(true);
                $t->timestamps();
                $t->string('created_by', 100)->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->foreign('help_topic_id')->references('id_help_topic')->on('sys_help_topic')->cascadeOnDelete();
                $t->index(['help_topic_id', 'line_type', 'sort_order'], 'idx_help_line_topic');
                $t->comment('Help: how-to steps and field explanations of a help topic');
            });
        }

        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $row = fn (array $r) => $menu()->where('code', $r['code'])->exists() ?: $menu()->insert($r + ['activate' => 1, 'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);

        // Input isi help: Setup > Settings (aturan "semua data wajib punya inputan").
        if ($settings = $menu()->where('code', 'SET_GRP_SETTINGS')->value('id')) {
            $row(['parent_id' => $settings, 'code' => 'SET_HELP_TOPIC', 'label' => 'Help Topics', 'icon' => 'fa-solid fa-book-open', 'menu_type' => 'item',
                'object_type' => 'grid', 'target' => 'v2:setup-help-topics', 'sort_order' => 47]);
            $row(['parent_id' => $settings, 'code' => 'SET_HELP_LINE', 'label' => 'Help Lines', 'icon' => 'fa-solid fa-list-check', 'menu_type' => 'item',
                'object_type' => 'grid', 'target' => 'v2:setup-help-lines', 'sort_order' => 48]);
        }
        // Help Center dibaca semua user -> modul GENERAL (terbuka untuk semua yang login).
        if ($general = $menu()->where('code', 'GENERAL')->value('id')) {
            $row(['parent_id' => $general, 'code' => 'GENERAL_HELP', 'label' => 'Help', 'icon' => 'fa-solid fa-circle-question', 'menu_type' => 'group',
                'sort_order' => 90, 'required_object' => 'GENERAL']);
            $row(['parent_id' => $menu()->where('code', 'GENERAL_HELP')->value('id'), 'code' => 'GENERAL_HELP_CENTER', 'label' => 'Help Center',
                'icon' => 'fa-solid fa-life-ring', 'menu_type' => 'item', 'object_type' => 'report', 'target' => 'v2:help-center', 'sort_order' => 10,
                'required_object' => 'GENERAL']);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['GENERAL_HELP_CENTER', 'GENERAL_HELP', 'SET_HELP_LINE', 'SET_HELP_TOPIC'])->delete();
        Schema::connection('tm2sys')->dropIfExists('sys_help_line');
        Schema::connection('tm2sys')->dropIfExists('sys_help_topic');
    }
};
