<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Support\Localization\Dictionary;
use Devlib\Locale;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

// Bahasa tampilan ikut AKUN user (sec_user.lang_code), bukan cookie perangkat: pilihan di x-dl::lang-switch disimpan ke
// akun lalu halaman dimuat ulang; pesan (flash / error / validasi) diterjemahkan di tempat pesan dibuat.
class LanguageAccountTest extends TestCase
{
    private ?SecUser $user = null;

    private ?string $original = null;

    protected function setUp(): void
    {
        parent::setUp();
        Locale::languagesUsing(null);
    }

    protected function tearDown(): void
    {
        if ($this->user) {
            DB::connection('tm2sys')->table('sec_user')->where('id_user', $this->user->getKey())->update(['lang_code' => $this->original]);
        }
        DB::connection('tm2sys')->table('sys_dictionary')->where('context', 'ui')->where('source_en', 'like', 'Zq %')->delete();
        Dictionary::flush();
        Auth::logout();
        parent::tearDown();
    }

    private function member(string $lang): SecUser
    {
        $user = new SecUser;
        $user->lang_code = $lang;   // tidak disimpan: cukup untuk Auth::user()->lang_code

        return $user;
    }

    public function test_account_language_wins_over_the_device_cookie(): void
    {
        config(['devlib.lang' => 'id']);
        $_COOKIE['dl_lang'] = 'id';
        $this->assertSame('id', Locale::lang(), 'tamu: cookie perangkat');

        Auth::setUser($this->member('en'));
        $this->assertSame('en', Locale::lang(), 'user login: bahasa akunnya, bukan cookie');

        Auth::setUser($this->member('xx'));
        $this->assertSame('id', Locale::lang(), 'kode bahasa yang tidak dikenal diabaikan');
        unset($_COOKIE['dl_lang']);
    }

    public function test_print_language_applies_to_that_request_only(): void
    {
        Auth::setUser($this->member('id'));
        request()->query->set('print_lang', 'en');
        $this->assertSame('en', Locale::lang());
        request()->query->remove('print_lang');
        $this->assertSame('id', Locale::lang());
    }

    public function test_language_switch_lists_the_active_languages_and_reloads_for_a_signed_in_user(): void
    {
        config(['devlib.lang' => 'id']);
        $guest = Blade::render('<x-dl::lang-switch />');
        $this->assertStringContainsString('data-lang="id"', $guest);
        $this->assertStringContainsString('data-lang="en"', $guest);
        $this->assertStringNotContainsString('data-reload', $guest, 'halaman tamu berganti di tempat');

        Auth::setUser($this->member('en'));
        $html = Blade::render('<x-dl::lang-switch />');
        $this->assertStringContainsString('data-reload', $html, 'user login: simpan ke akun + muat ulang');
        $this->assertMatchesRegularExpression('/is-active[^>]*data-lang="en"/', $html, 'bendera aktif = bahasa akun');
    }

    public function test_choosing_a_language_is_saved_to_the_account(): void
    {
        $this->user = SecUser::query()->where('id_user', '>', 1)->where('activate', 1)->orderBy('id_user')->firstOrFail();
        $this->original = $this->user->lang_code;
        $target = $this->original === 'en' ? 'id' : 'en';

        $this->actingAs($this->user)->post(route('v2.lang'), ['lang' => $target])->assertNoContent();
        $this->assertSame($target, DB::connection('tm2sys')->table('sec_user')->where('id_user', $this->user->getKey())->value('lang_code'));

        $this->actingAs($this->user)->post(route('v2.lang'), ['lang' => 'xx'])->assertStatus(422);
        $this->assertSame($target, DB::connection('tm2sys')->table('sec_user')->where('id_user', $this->user->getKey())->value('lang_code'), 'bahasa tak dikenal ditolak');
    }

    public function test_tf_translates_the_sentence_then_fills_the_values(): void
    {
        $db = DB::connection('tm2sys');
        $key = $db->table('sys_dictionary')->insertGetId(['context' => 'ui', 'source_en' => 'Zq Quotation :no created by :name.', 'created_at' => now(), 'created_by' => 'test']);
        $db->table('sys_dictionary_lang')->insert(['id_dictionary' => $key, 'lang_code' => 'id', 'text' => 'Zq Quotation :no dibuat oleh :name.', 'created_at' => now()]);
        Dictionary::flush();

        config(['devlib.lang' => 'id']);
        $this->assertSame('Zq Quotation QU2600001 dibuat oleh Budi.', tf('Zq Quotation :no created by :name.', ['no' => 'QU2600001', 'name' => 'Budi']));
        config(['devlib.lang' => 'en']);
        $this->assertSame('Zq Quotation QU2600001 created by Budi.', tf('Zq Quotation :no created by :name.', ['no' => 'QU2600001', 'name' => 'Budi']));
        $this->assertSame('Zq No values.', tf('Zq No values.'));
    }

    public function test_validation_messages_follow_the_display_language(): void
    {
        App::setLocale('id');
        $this->assertSame('Alasan wajib diisi.', Validator::make([], ['reason' => 'required'], [], ['reason' => 'Alasan'])->errors()->first());
        App::setLocale('en');
        $this->assertSame('The Reason field is required.', Validator::make([], ['reason' => 'required'], [], ['reason' => 'Reason'])->errors()->first());
    }
}
