<?php

namespace Tests\Feature;

use App\Models\Sys\SysDictionary;
use App\Support\Localization\Dictionary;
use App\Support\V2\Grid\Setup\TranslationDictionaryLookup;
use App\Support\V2\Print\PrintDocs;
use Devlib\Lang;
use Devlib\Locale;
use Devlib\PrintDoc\Document;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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
            ->orWhere(fn ($q) => $q->whereIn('context', ['ui', 'print'])->where('source_en', 'like', 'Zq %'))->delete();  // baris _lang ikut (cascade)
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

    // Nilai data (no. dokumen, nama customer) di prop teks: raw() -> label tetap diterjemahkan, nilainya tidak pernah sampai ke kamus.
    public function test_data_values_marked_raw_never_reach_the_dictionary(): void
    {
        $this->entry('Zq Order', 'Zq Pesanan');
        config(['devlib.lang' => 'id']);
        $seen = [];
        Lang::translateUsing(function (string $en, string $lang) use (&$seen) {
            $seen[] = $en;

            return Dictionary::t($en, 'ui', $lang);
        });

        $html = Blade::render('<x-dl::card :title="\'Zq Order · \'.raw($no)" :description="raw($name.\' · \'.$status)" /><x-dl::badge :label="raw($status)" />',
            ['no' => 'CO2600001', 'name' => 'PT. Zq Jaya', 'status' => 'Draft']);

        $this->assertStringContainsString('<b>Zq Pesanan · CO2600001</b>', $html, 'label diterjemahkan, nomor dokumen apa adanya');
        $this->assertStringContainsString('<small>PT. Zq Jaya · Draft</small>', $html);
        $this->assertSame(['Zq Order'], $seen, 'hanya label yang dikirim ke kamus');
        $this->assertFalse(Lang::hasRaw($html), 'penanda raw() tidak sampai ke layar');
        $this->assertSame('Zq Pesanan: 12', t('Zq Order: '.raw(12)), 't() memperlakukan raw() sama seperti komponen');
    }

    public function test_data_like_and_already_translated_text_is_not_registered(): void
    {
        $this->entry('Zq Saved.', 'Zq Beres tercatat.', self::CTX);
        config(['devlib.lang' => 'id']);

        $added = Dictionary::register(['Zq Order · CO2600001', 'Zq Complaint · Draft #5', 'Zq Order · '.raw('PT. Zq Jaya'), 'Zq Beres tercatat.', 'Zq Plain Label'], self::CTX);

        $this->assertSame(1, $added, 'nomor dokumen, Draft #n, teks ber-raw() dan teks hasil terjemahan tidak dicatat');
        $this->assertSame(['Zq Plain Label', 'Zq Saved.'], SysDictionary::where('context', self::CTX)->orderBy('source_en')->pluck('source_en')->all());
        $this->assertTrue(Dictionary::looksLikeData('Sales Return · CC2600001'));
        $this->assertFalse(Dictionary::looksLikeData('Net 30 Days'));
        $this->assertFalse(Dictionary::looksLikeData('mis. Art Carton 260gsm, 65×100cm'));
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

        $this->expectException(ValidationException::class);
        $grid->save([['id' => 'new2', 'context' => self::CTX, 'source_en' => 'Zq Valid Until', 'lang_id' => 'x']]);  // dobel
    }

    // Migration mengisi kamus lewat seed(): terjemahan yang sudah diisi penerjemah tidak ditimpa.
    public function test_seed_fills_only_missing_translations(): void
    {
        $this->entry('Zq Warehouse', 'Zq Gudang Penerjemah', self::CTX);

        $n = Dictionary::seed(['Zq Warehouse' => 'Zq Gudang', 'Zq Delivery' => 'Zq Pengiriman'], self::CTX);

        $this->assertSame(1, $n);
        $this->assertSame('Zq Gudang Penerjemah', Dictionary::t('Zq Warehouse', self::CTX, 'id'));
        $this->assertSame('Zq Pengiriman', Dictionary::t('Zq Delivery', self::CTX, 'id'));
        $this->assertSame('Zq Delivery', Dictionary::t('Zq Delivery', self::CTX, 'en'));
    }

    // Kunci kamus tidak peka huruf (collation DB): bentuk huruf besar / kecil memakai baris yang sama, hurufnya ikut sumber.
    public function test_lookup_ignores_case_and_keeps_source_case(): void
    {
        $this->entry('Zq Cancelled', 'Zq Dibatalkan', self::CTX);

        $this->assertSame('ZQ DIBATALKAN', Dictionary::t('ZQ CANCELLED', self::CTX, 'id'));
        $this->assertSame('zq dibatalkan', Dictionary::t('zq cancelled', self::CTX, 'id'));
        $this->assertSame('Zq Dibatalkan', Dictionary::t('Zq cancelled', self::CTX, 'id'));
        $this->assertSame('ZQ CANCELLED', Dictionary::t('ZQ CANCELLED', self::CTX, 'en'));
    }

    // Istilah cetak = kamus konteks 'print' (dulu array PHP): Document::t() memakai kamus itu di semua bahasa selain English.
    public function test_print_terms_come_from_print_context(): void
    {
        $this->entry('Zq Received by', 'Zq Diterima oleh', 'print');
        PrintDocs::boot();

        config(['devlib.lang' => 'id']);
        $this->assertSame('Zq Diterima oleh', Document::t('Zq Received by'));
        $this->assertNull(Dictionary::find('Zq Received by', 'print', 'en'));
    }

    // English sumber diganti: terjemahan ikut; teks tujuan sudah ada -> digabung, terjemahan tujuan menang.
    public function test_rename_keeps_translations(): void
    {
        $this->entry('Zq Qty Order', 'Zq Jumlah Pesan', self::CTX);
        $this->assertTrue(Dictionary::rename('Zq Qty Order', 'Zq Order Qty', self::CTX));
        $this->assertSame('Zq Jumlah Pesan', Dictionary::t('Zq Order Qty', self::CTX, 'id'));

        $this->entry('Zq Order Quantity', 'Zq Kuantitas Pesanan', self::CTX);
        $this->assertTrue(Dictionary::rename('Zq Order Qty', 'Zq Order Quantity', self::CTX));
        $this->assertSame('Zq Kuantitas Pesanan', Dictionary::t('Zq Order Quantity', self::CTX, 'id'));
        $this->assertSame(['Zq Order Quantity'], SysDictionary::where('context', self::CTX)->pluck('source_en')->all());
        $this->assertFalse(Dictionary::rename('Zq Not There', 'Zq Other', self::CTX));
    }

    public function test_translation_dictionary_screen_merges_renamed_source_and_flags_missing(): void
    {
        $grid = new TranslationDictionaryLookup;
        $rows = collect($grid->save([
            ['id' => 'new1', 'context' => self::CTX, 'source_en' => 'Zq Due Date', 'lang_id' => 'Zq Jatuh Tempo', 'note' => '', 'activate' => true],
            ['id' => 'new2', 'context' => self::CTX, 'source_en' => 'Zq Due', 'lang_id' => '', 'note' => '', 'activate' => true],
        ]));
        $this->assertMatchesRegularExpression('/^missing: .*\\bid\\b/', $rows->firstWhere('source_en', 'Zq Due')['missing']);
        $this->assertDoesNotMatchRegularExpression('/\\bid\\b/', $rows->firstWhere('source_en', 'Zq Due Date')['missing']);

        $due = $rows->firstWhere('source_en', 'Zq Due');
        $grid->save([['id' => $due['id'], 'context' => self::CTX, 'source_en' => 'Zq Due Date', 'note' => '', 'activate' => true]]);

        $this->assertSame(['Zq Due Date'], SysDictionary::where('context', self::CTX)->pluck('source_en')->all());
        $this->assertSame('Zq Jatuh Tempo', Dictionary::t('Zq Due Date', self::CTX, 'id'));
    }
}
