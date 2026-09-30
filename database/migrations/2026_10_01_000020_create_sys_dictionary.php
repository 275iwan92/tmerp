<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Kamus penerjemah (LOCKED user 2026-10-01): database + formula = English; bahasa lain HANYA tampilan layar & cetakan.
// sys_dictionary = satu teks English per konteks (sumber); sys_dictionary_lang = terjemahannya per bahasa.
// English tidak punya baris terjemahan -- teks sumbernya sendiri; terjemahan kosong -> tampil English.
// context: 'ui' (label layar/cetak), 'date.month_short', '<db>.<tabel>.<kolom>' (nilai lookup/master), dst.
// sys_languages dilengkapi: native_name (日本語), intl_locale (Intl browser), activate, sort_order.
return new class extends Migration
{
    private const MONTHS_ID = ['Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr', 'May' => 'Mei', 'Jun' => 'Jun',
        'Jul' => 'Jul', 'Aug' => 'Agu', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des'];

    public function up(): void
    {
        $s = Schema::connection('tm2sys');
        $s->table('sys_languages', function (Blueprint $t) {
            $t->string('native_name', 60)->nullable()->after('name')->comment('nama bahasa dalam bahasanya sendiri, mis. 日本語');
            $t->string('intl_locale', 20)->nullable()->after('native_name')->comment('locale Intl browser, mis. id-ID, ja-JP');
            $t->boolean('activate')->default(true)->after('is_default');
            $t->unsignedSmallInteger('sort_order')->default(0)->after('activate');
        });
        $db = DB::connection('tm2sys');
        $db->table('sys_languages')->where('code', 'en')->update(['native_name' => 'English', 'intl_locale' => 'en-US', 'sort_order' => 1]);
        $db->table('sys_languages')->where('code', 'id')->update(['native_name' => 'Bahasa Indonesia', 'intl_locale' => 'id-ID', 'sort_order' => 2]);

        $s->create('sys_dictionary', function (Blueprint $t) {
            $t->id('id_dictionary');
            $t->string('context', 100)->comment("ui | date.month_short | <db>.<tabel>.<kolom> ...");
            $t->string('source_en', 255)->comment('teks English (bahasa dasar database)');
            $t->string('note', 255)->nullable()->comment('petunjuk untuk penerjemah');
            $t->boolean('activate')->default(true);
            $t->timestamp('created_at')->nullable();
            $t->string('created_by', 50)->nullable();
            $t->timestamp('updated_at')->nullable();
            $t->string('updated_by', 50)->nullable();
            $t->unique(['context', 'source_en']);
        });
        $s->create('sys_dictionary_lang', function (Blueprint $t) {
            $t->id('id_dictionary_lang');
            $t->unsignedBigInteger('id_dictionary');
            $t->string('lang_code', 10)->comment('sys_languages.code');
            $t->string('text', 255);
            $t->timestamp('created_at')->nullable();
            $t->string('created_by', 50)->nullable();
            $t->timestamp('updated_at')->nullable();
            $t->string('updated_by', 50)->nullable();
            $t->unique(['id_dictionary', 'lang_code']);
            $t->foreign('id_dictionary')->references('id_dictionary')->on('sys_dictionary')->cascadeOnDelete();
        });

        $now = now();
        foreach (self::MONTHS_ID as $en => $id) {
            $key = $db->table('sys_dictionary')->insertGetId(['context' => 'date.month_short', 'source_en' => $en, 'note' => 'singkatan bulan 3 huruf (dd-Mmm-yyyy)',
                'created_at' => $now, 'created_by' => 'system']);
            $db->table('sys_dictionary_lang')->insert(['id_dictionary' => $key, 'lang_code' => 'id', 'text' => $id, 'created_at' => $now, 'created_by' => 'system']);
        }
    }

    public function down(): void
    {
        $s = Schema::connection('tm2sys');
        $s->dropIfExists('sys_dictionary_lang');
        $s->dropIfExists('sys_dictionary');
        $s->table('sys_languages', fn (Blueprint $t) => $t->dropColumn(['native_name', 'intl_locale', 'activate', 'sort_order']));
    }
};
