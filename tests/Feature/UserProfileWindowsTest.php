<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

// Menu profil 2026-09-23: jendela Configuration (Themes/Language/Grid segera)
// + Aktivitas saya, dibuka lewat x-ui.window. Tema/bahasa johiwan diubah lalu
// dikembalikan PERSIS ke nilai yang direkam di awal (bukan nilai tebakan).
class UserProfileWindowsTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_configuration_sections_theme_and_language_persist_per_user(): void
    {
        $user = $this->admin();
        $origTheme = $user->theme_code;
        $origLang = $user->lang_code;
        $this->actingAs($user);

        try {
            // Teks yang dicek berbahasa Indonesia -- paksa 'id' selama test (akun
            // johiwan bisa sedang ber-lang 'en'); nilai asli dikembalikan di finally.
            SecUser::whereKey($user->getKey())->update(['lang_code' => 'id']);
            $user->lang_code = 'id';

            $c = Livewire::test('shell.user-configuration');
            $this->assertSame('themes', $c->get('section'));
            $c->assertSee('Pastel')->assertSee('Hitam')->assertSee('Klasik');
            $this->assertCount(4, $c->instance()->themeMatrix()['rows']);

            $c->call('setSection', 'language')->assertSee('English');
            $c->call('setSection', 'grid')->assertSee('Kerapatan baris');
            $c->call('setSection', 'nope');
            $this->assertSame('grid', $c->get('section'));

            $target = $origTheme === 'dark_green' ? 'pastel_blue' : 'dark_green';
            $c->call('setTheme', $target);
            $this->assertSame($target, $user->fresh()->theme_code);
            $c->call('setTheme', 'bukan-tema');
            $this->assertSame($target, $user->fresh()->theme_code);

            $c->call('setLang', 'en');
            $this->assertSame('en', $user->fresh()->lang_code);
        } finally {
            SecUser::whereKey($user->getKey())->update(['theme_code' => $origTheme, 'lang_code' => $origLang]);
        }

        $this->assertSame($origTheme, $user->fresh()->theme_code);
        $this->assertSame($origLang, $user->fresh()->lang_code);
    }

    public function test_activity_loads_logins_and_documents_only_when_opened(): void
    {
        $this->actingAs($this->admin());

        $c = Livewire::test('shell.user-activity');
        $c->assertSee('Memuat');
        $c->call('load')->assertSee('Riwayat login')->assertSee('Dokumen terakhir');

        foreach ($c->instance()->documents() as $d) {
            $this->assertContains($d['action'], ['Dibuat', 'Diubah']);
        }
    }

    public function test_profile_menu_items_open_windows_for_every_user(): void
    {
        foreach (['johiwan', 'rina'] as $login) {
            $this->actingAs(SecUser::where('login', $login)->firstOrFail());
            $items = Livewire::test('shell.user-menu')->instance()->userMenuItems()->flatten(1)->keyBy('code');

            $this->assertSame('configWinOpen', $items['UM_CONFIG']->action_target, $login);
            $this->assertSame('activityWinOpen', $items['UM_ACTIVITY']->action_target, $login);
        }

        $html = Livewire::test('shell.user-panels')->html();
        $this->assertStringContainsString("tmWindow({ open: 'configWinOpen'", str_replace('&quot;', "'", html_entity_decode($html)));
        $this->assertStringContainsString('activityWinOpen', $html);
    }

    public function test_window_component_renders_controls(): void
    {
        $html = Blade::render('<x-ui.window open="demoWin" id="demo" title="Demo" icon="fa-solid fa-star">isi</x-ui.window>');

        $this->assertStringContainsString('tm-win', $html);
        $this->assertStringContainsString('toggleMin()', $html);
        $this->assertStringContainsString('toggleMax()', $html);
        $this->assertStringContainsString('close()', $html);
        $this->assertStringContainsString('dragStart($event)', $html);
        $this->assertStringContainsString('isi', $html);
    }
}
