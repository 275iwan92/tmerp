<?php

namespace Tests\Feature;

use App\Models\Sys\SysDictionary;
use App\Support\Localization\Dictionary;
use App\Support\V2\Grid\Setup\TranslationDictionaryLookup;
use Devlib\Locale;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Modul baru otomatis ID + EN (user 2026-10-01): label English di komponen x-dl::* diterjemahkan kamus sys_dictionary
// tanpa kode tambahan; teks baru tercatat; tombol tetap English; layar Translation Dictionary menyimpan terjemahan.
class MultiLanguageAutoTest extends TestCase
{
    private const CTX = 'test.multilang';

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            $this->markTestSkipped('sys_dictionary belum dimigrasi.');
        }
        Locale::languagesUsing(null);
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        Dictionary::flush();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        DB::connection('tm2sys')->table('sys_dictionary')->where('context', self::CTX)
            ->orWhere(fn ($q) => $q->where('context', 'ui')->where('source_en', 'like', 'Zq %'))->delete();  // baris _lang ikut (cascade)
    }

    private function entry(string $en, string $id, string $ctx = 'ui'): void
    {
        $key = DB::connection('tm2sys')->table('sys_dictionary')->insertGetId(['context' => $ctx, 'source_en' => $en, 'created_at' => now(), 'created_by' => 'test']);
        DB::connection('tm2sys')->table('sys_dictionary_lang')->insert(['id_dictionary' => $key, 'lang_code' => 'id', 'text' => $id, 'created_at' => now()]);
        Dictionary::flush();
    }

    public function test_devlib_components_translate_english_labels_automatically(): void
    {
        $this->entry('Zq Customer Name', 'Zq Nama Pelanggan');
        $this->entry('Zq Save', 'Zq Simpan');
        $tpl = '<x-dl::field.text name="c" label="Zq Customer Name" /><x-dl::button label="Zq Save" />'
            .'<x-dl::table :columns="[\'a\' => \'Zq Customer Name\']" :rows="[]" />';

        config(['devlib.lang' => 'id']);
        $html = Blade::render($tpl);
        $this->assertStringContainsString('Zq Nama Pelanggan', $html, 'label field & kolom tabel diterjemahkan');
        $this->assertSame(2, substr_count($html, 'Zq Nama Pelanggan'));
        $this->assertStringContainsString('Zq Save', $html, 'tombol tetap English');
        $this->assertStringNotContainsString('Zq Simpan', $html);

        config(['devlib.lang' => 'en']);
        $this->assertStringNotContainsString('Zq Nama Pelanggan', Blade::render($tpl), 'English = teks sumber');
    }

    public function test_t_helper_and_blade_directive(): void
    {
        $this->entry('Zq Delivery Date', 'Zq Tanggal Kirim');
        config(['devlib.lang' => 'id']);
        $this->assertSame('Zq Tanggal Kirim', t('Zq Delivery Date'));
        $this->assertSame('Zq Tanggal Kirim', trim(Blade::render("@t('Zq Delivery Date')")));
        $this->assertSame('Zq Unknown Text', t('Zq Unknown Text'), 'belum ada terjemahan -> English');
    }

    public function test_new_english_text_is_registered_but_indonesian_is_not(): void
    {
        $added = Dictionary::register(['Zq Payment Terms', 'Zq Tanggal kirim barang', 'Zq Payment Terms'], self::CTX);
        $this->assertSame(1, $added, 'teks English tercatat sekali; label Indonesia tidak');
        $this->assertTrue(SysDictionary::where('context', self::CTX)->where('source_en', 'Zq Payment Terms')->exists());
        $this->assertFalse(SysDictionary::where('context', self::CTX)->where('source_en', 'like', '%Tanggal%')->exists());
        $this->assertSame(0, Dictionary::register(['Zq Payment Terms'], self::CTX), 'sudah ada -> tidak dobel');
    }

    public function test_translation_dictionary_screen_saves_translations(): void
    {
        $grid = new TranslationDictionaryLookup;
        $this->assertContains('lang_id', array_column($grid->columns(), 'key'));

        $rows = $grid->save([['id' => 'new1', 'context' => self::CTX, 'source_en' => 'Zq Valid Until', 'lang_id' => 'Zq Berlaku s/d', 'note' => '', 'activate' => true]]);
        $row = collect($rows)->firstWhere('source_en', 'Zq Valid Until');
        $this->assertSame('Zq Berlaku s/d', $row['lang_id']);
        $this->assertSame('Zq Berlaku s/d', Dictionary::t('Zq Valid Until', self::CTX, 'id'));

        $grid->save([['id' => $row['id'], 'context' => self::CTX, 'source_en' => 'Zq Valid Until', 'lang_id' => '', 'note' => '', 'activate' => true]]);
        $this->assertSame('Zq Valid Until', Dictionary::t('Zq Valid Until', self::CTX, 'id'), 'sel dikosongkan -> kembali English');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $grid->save([['id' => 'new2', 'context' => self::CTX, 'source_en' => 'Zq Valid Until', 'lang_id' => 'x']]);  // dobel
    }
}
