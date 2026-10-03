<?php

namespace Tests\Feature;

use App\Support\Localization\Dictionary;
use Devlib\Lang;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Translation Dictionary dua panel (user 2026-10-03): kamus proyek (sys_dictionary) menang atas kata bawaan DevLib
// (Devlib\Lang::DICT), dan teks baru tercatat dengan kelompok pesan dari objek yang menampilkannya.
class DictionaryGroupsTest extends TestCase
{
    private const SRC = 'Zq Dictionary Groups Test';

    private ?string $closeId = null;

    private ?int $closeRow = null;

    protected function setUp(): void
    {
        parent::setUp();
        Locale::langUsing(fn () => 'id');
        Dictionary::flush();
    }

    protected function tearDown(): void
    {
        $db = DB::connection('tm2sys');
        if ($this->closeRow) {
            $db->table('sys_dictionary_lang')->where('id_dictionary', $this->closeRow)->where('lang_code', 'id')->delete();
            if ($this->closeId !== null) {
                $db->table('sys_dictionary_lang')->insert(['id_dictionary' => $this->closeRow, 'lang_code' => 'id', 'text' => $this->closeId, 'created_at' => now(), 'created_by' => 'devlib']);
            }
        }
        $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', self::SRC)->delete();
        Dictionary::recordInConsole(false);
        Lang::hint(null);
        Locale::langUsing(null);
        Dictionary::flush();
        parent::tearDown();
    }

    public function test_project_dictionary_overrides_devlib_default(): void
    {
        $db = DB::connection('tm2sys');
        $this->assertSame('Tutup', Lang::text('Close'), 'DevLib default for Close is Tutup');

        $this->closeRow = (int) $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', 'Close')->value('id_dictionary');
        $this->assertGreaterThan(0, $this->closeRow, 'DevLib words are seeded into sys_dictionary (origin devlib)');
        $this->assertSame('devlib', $db->table('sys_dictionary')->where('id_dictionary', $this->closeRow)->value('origin'));
        $this->closeId = $db->table('sys_dictionary_lang')->where('id_dictionary', $this->closeRow)->where('lang_code', 'id')->value('text');

        $db->table('sys_dictionary_lang')->where('id_dictionary', $this->closeRow)->where('lang_code', 'id')->delete();
        $db->table('sys_dictionary_lang')->insert(['id_dictionary' => $this->closeRow, 'lang_code' => 'id', 'text' => 'Tutup Dokumen', 'created_at' => now(), 'created_by' => 'test']);
        Dictionary::flush();

        $this->assertSame('Tutup Dokumen', Lang::text('Close'), 'the project dictionary wins over Devlib\Lang::DICT');
        $this->assertSame('Tutup Dokumen', t('Close'));
    }

    public function test_new_text_is_recorded_with_the_group_of_the_showing_object(): void
    {
        Dictionary::recordInConsole(true);
        Lang::hint('buttons');
        Lang::text(self::SRC);
        Lang::hint(null);
        Dictionary::flushPending();

        $row = DB::connection('tm2sys')->table('sys_dictionary')->where('context', 'ui')->where('source_en', self::SRC)->first();
        $this->assertNotNull($row, 'text recorded');
        $this->assertSame('buttons', $row->group_code);
        $this->assertSame('app', $row->origin);
        $this->assertNotNull(DB::connection('tm2sys')->table('sys_dictionary_group')->where('code', 'buttons')->first(), 'group exists in sys_dictionary_group');
    }

    public function test_message_helper_records_in_the_messages_group(): void
    {
        Dictionary::recordInConsole(true);
        tf(self::SRC);
        Dictionary::flushPending();

        $this->assertSame('messages', DB::connection('tm2sys')->table('sys_dictionary')->where('context', 'ui')->where('source_en', self::SRC)->value('group_code'));
    }
}
