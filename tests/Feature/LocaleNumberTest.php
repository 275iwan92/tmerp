<?php

namespace Tests\Feature;

use App\Support\Localization\Dictionary;
use Devlib\DateTimeValue;
use Devlib\DateValue;
use Devlib\NumberValue;
use Devlib\TextValue;
use Devlib\TimeValue;
use Devlib\Locale;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Angka & bulan ikut bahasa tampilan, DB tetap mentah/English (LOCKED user 2026-10-01).
// Bolak-balik: nilai DB -> tampil (id/en) -> dibaca lagi (ketikan) -> kanonik yang sama.
class LocaleNumberTest extends TestCase
{
    private array $dictIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Locale::languagesUsing(null);  // bawaan DevLib id/en, tidak bergantung isi sys_languages
    }

    protected function tearDown(): void
    {
        if ($this->dictIds) {
            DB::connection('tm2sys')->table('sys_dictionary')->whereIn('id_dictionary', $this->dictIds)->delete();  // baris _lang ikut (cascade)
        }
        Dictionary::flush();
        parent::tearDown();
    }

    public function test_format_follows_display_language(): void
    {
        $this->assertSame('1.234.567,50', Locale::number('1234567.5', 2, 'id'));
        $this->assertSame('1,234,567.50', Locale::number('1234567.5', 2, 'en'));
        $this->assertSame('12,5', Locale::decimal(12.5, 2, 'id'));
        $this->assertSame('1,250', Locale::decimal(1250, 2, 'en'));
        $this->assertSame('1234,5', Locale::raw('1234.50', 'id'));
        $this->assertSame('1234.5', Locale::raw('1234.50', 'en'));
        $this->assertSame('1250000', Locale::raw('1250000.00', 'id'));
        $this->assertSame('IDR 1.250.000', Locale::money('1250000.00', 'IDR', 0, 'id'));
    }

    public function test_parse_human_input_per_language(): void
    {
        $cases = [
            // [ketikan, bahasa, kanonik]
            ['1.234,5', 'id', '1234.5'], ['1.234.567', 'id', '1234567'], ['1.234', 'id', '1234'], ['12,5', 'id', '12.5'],
            ['12.5', 'id', '12.5'], ['12,', 'id', '12'], ['Rp 1.250.000', 'id', '1250000'], ['-0,75', 'id', '-0.75'],
            ['1,234.5', 'en', '1234.5'], ['1,234,567', 'en', '1234567'], ['1,234', 'en', '1234'], ['12.5', 'en', '12.5'],
            ['1.234', 'en', '1.234'], ['1,5', 'en', '1.5'],
            // titik DAN koma: yang terakhir desimal, apa pun bahasanya (tempelan Excel)
            ['1.234,5', 'en', '1234.5'], ['1,234.5', 'id', '1234.5'],
            ['', 'id', null], ['abc', 'en', null], ['1,2,3', 'id', null], ['1.2.3', 'en', null],
        ];
        foreach ($cases as [$in, $lang, $want]) {
            $this->assertSame($want, Locale::parse($in, $lang), "parse('$in', $lang)");
        }
    }

    public function test_round_trip_display_type_save_display(): void
    {
        foreach (['id', 'en'] as $lang) {
            foreach (['1234.5', '0.125', '1250000', '-12.75', '1.234', '999999999.99'] as $db) {
                $shown = Locale::number($db, 3, $lang);           // tampil di layar/cetak
                $typed = Locale::raw($db, $lang);                 // isian saat diedit (ala sel Excel)
                $this->assertSame((float) $db, (float) Locale::parse($shown, $lang), "$lang tampil $shown");
                $this->assertSame((float) $db, (float) Locale::parse($typed, $lang), "$lang ketik $typed");
                $this->assertSame((float) $db, (float) Locale::input($db, $lang), "$lang kiriman kanonik $db");
            }
        }
    }

    // Server menerima kanonik dari JS; "1.234" kanonik tetap 1.234 (bukan seribu) di bahasa mana pun.
    public function test_form_post_is_canonical_first(): void
    {
        $this->assertSame('1.234', Locale::input('1.234', 'id'));
        $this->assertSame('1234567', Locale::input('1.234.567', 'id'));  // tanpa JS: dibaca sebagai ketikan
        $this->assertSame('0', Locale::input('000', 'en'));
        $this->assertNull(Locale::input('', 'id'));
        $this->assertSame('1250.75', Locale::input(1250.75));
    }

    public function test_month_short_name_follows_language(): void
    {
        $this->assertSame('10-Okt-2026', Locale::date('2026-10-10 12:00:00', 'short', 'id'));
        $this->assertSame('10-Oct-2026', Locale::date('2026-10-10 12:00:00', 'short', 'en'));
        $this->assertSame('17-Agu-2026', Locale::date('2026-08-17 12:00:00', 'short', 'id'));
        $this->assertNull(Locale::d(null));
    }

    public function test_money_and_decimal_fields_render_in_display_language(): void
    {
        foreach (['id' => ['1.250.000,00', '12,50'], 'en' => ['1,250,000.00', '12.50']] as $lang => [$money, $dec]) {
            config(['devlib.lang' => $lang]);
            $html = Blade::render('<x-dl::field.money name="m" :value="$m" /><x-dl::field.decimal name="d" :value="$d" />', ['m' => '1250000.00', 'd' => '12.5']);
            $this->assertStringContainsString('value="'.$money.'"', $html, $lang);
            $this->assertStringContainsString('value="'.$dec.'"', $html, $lang);
            $this->assertSame(2, substr_count($html, 'data-dl-num'), 'isian angka ditandai agar dikirim kanonik');
        }
    }

    public function test_print_lang_query_overrides_for_that_request_only(): void
    {
        config(['devlib.lang' => 'id']);
        $this->app->instance('request', \Illuminate\Http\Request::create('/x', 'GET', ['print_lang' => 'en']));
        $this->assertSame('en', Locale::lang());
        $this->app->instance('request', \Illuminate\Http\Request::create('/x', 'GET', ['print_lang' => 'xx']));
        $this->assertSame('id', Locale::lang());
    }

    // Kamus DB: sumber English + terjemahan; bahasa tanpa baris -> English. Juga menyuplai bulan ke Locale.
    public function test_dictionary_translates_english_source(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            $this->markTestSkipped('sys_dictionary belum dimigrasi di database ini (baru ddtsys).');
        }
        $db = DB::connection('tm2sys');
        $id = $db->table('sys_dictionary')->insertGetId(['context' => 'test.locale', 'source_en' => 'Net 30 Days', 'created_at' => now(), 'created_by' => 'test']);
        $this->dictIds[] = $id;
        $db->table('sys_dictionary_lang')->insert(['id_dictionary' => $id, 'lang_code' => 'id', 'text' => 'Bayar 30 Hari', 'created_at' => now()]);
        Dictionary::flush();

        $this->assertSame('Bayar 30 Hari', Dictionary::t('Net 30 Days', 'test.locale', 'id'));
        $this->assertSame('Net 30 Days', Dictionary::t('Net 30 Days', 'test.locale', 'en'));
        $this->assertSame('Net 30 Days', Dictionary::t('Net 30 Days', 'test.locale', 'ja'));
        $this->assertSame('Unknown', Dictionary::t('Unknown', 'test.locale', 'id'));

        $langs = Dictionary::languages();
        $this->assertSame('Okt', $langs['id']['months'][9] ?? null, 'bulan id dari kamus date.month_short');
        $this->assertSame(',', $langs['id']['dec']);
    }

    // Satu objek tanggal (DateValue) untuk ketik & tampil; kembarannya DlDate di core.js.
    public function test_date_value_parses_typed_input_and_displays_one_format(): void
    {
        $y = (int) now('Asia/Jakarta')->year;
        $cases = ['10-10-2026' => '2026-10-10', '10/10/26' => '2026-10-10', '10 okt 2026' => '2026-10-10', '10-Oct-2026' => '2026-10-10',
            '10-Okt-2026' => '2026-10-10', '2026-10-10' => '2026-10-10', '2026-10-10 23:15:00' => '2026-10-10', '17 agt' => $y.'-08-17',
            '5.1.2027' => '2027-01-05', '31-02-2026' => null, '10-Foo-2026' => null, 'besok' => null, '' => null];
        foreach ($cases as $in => $iso) {
            $this->assertSame($iso, DateValue::of($in)?->iso(), "DateValue::of('$in')");
        }
        $d = DateValue::of('2026-10-10');
        $this->assertSame('10-Okt-2026', $d->display('id'));
        $this->assertSame('10-Oct-2026', $d->display('en'));
        $this->assertSame('2026-10-10', DateValue::of('2026-10-09T20:00:00Z')->iso(), 'UTC malam = besok WIB');
        $this->assertSame('2026-10-10', DateValue::of($d->display('id'))->iso(), 'tampilan bisa dibaca lagi');
    }

    // Objek nilai DevLib: angka, jam, tanggal+jam, teks -- input & tampilan dari satu objek.
    public function test_value_objects_for_number_time_datetime_text(): void
    {
        $n = NumberValue::parse('1.234,5', 'id');
        $this->assertSame('1234.5', $n->canonical);
        $this->assertSame('1,234.50', $n->display(2, 'en'));
        $this->assertSame('1234,5', $n->raw('id'));
        $this->assertSame('12', NumberValue::of('12.000')->trimmed(2, 'id'));
        $this->assertNull(NumberValue::parse('x'));

        foreach (['8' => '08:00', '830' => '08:30', '0830' => '08:30', '8:30' => '08:30', '08.30' => '08:30', '2400' => null, '8:61' => null] as $in => $want) {
            $this->assertSame($want, TimeValue::of((string) $in)?->display(), "TimeValue('$in')");
        }
        $dt = DateTimeValue::of('10-10-26 8:30');
        $this->assertSame('2026-10-10 08:30:00', $dt->iso());
        $this->assertSame('10-Okt-2026 08:30', $dt->display('id'));
        $this->assertSame('2026-10-10 08:30:00', DateTimeValue::of('2026-10-10 08:30:00')->iso());
        $this->assertSame('2026-10-10 00:00:00', DateTimeValue::of('10 okt 2026')->iso(), 'tanggal saja, bukan jam 20:26');
        $this->assertSame('2026-10-10 03:00:00', DateTimeValue::of('2026-10-09T20:00:00Z')->iso(), 'UTC -> WIB');
        config(['devlib.lang' => 'en']);
        $this->assertSame('10-Oct-2026 08:30', Locale::stamp('2026-10-10 08:30:00'));

        $this->assertSame('Kertas Duplex', TextValue::of('  kertas   duplex ', 'capitalize')->value);
        $this->assertSame('ABC', (string) TextValue::of('abc', 'uppercase'));
        $this->assertSame('—', TextValue::of('  ')->display());
    }
}
