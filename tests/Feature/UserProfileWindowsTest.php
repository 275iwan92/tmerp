<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

// Menu profil 2026-09-23: jendela Configuration (Themes/Language; bagian Grid dihapus
// atas keputusan user 2026-10-02) + Aktivitas saya, dibuka sebagai drawer kanan. Tema/bahasa johiwan diubah lalu
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

            $this->assertSame(['themes', 'language'], array_keys($c->instance()->sections()));
            $c->call('setSection', 'language')->assertSee('English');
            // Bagian yang tidak ada (termasuk 'grid' yang sudah dihapus) diabaikan.
            $c->call('setSection', 'grid')->assertDontSee('Kerapatan baris');
            $c->call('setSection', 'nope');
            $this->assertSame('language', $c->get('section'));

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

    // Keputusan user 2026-09-23: SEMUA menu profil = drawer kanan (bukan jendela).
    public function test_every_profile_menu_item_opens_a_rendered_drawer(): void
    {
        foreach (['johiwan', 'rina', 'dummy_mgrops'] as $login) {
            $user = SecUser::where('login', $login)->firstOrFail();
            $this->actingAs($user);

            $items = Livewire::test('shell.user-menu')->instance()->userMenuItems()->flatten(1);
            $panels = Livewire::test('shell.user-panels')->html();
            $menu = html_entity_decode(Livewire::test('shell.user-menu')->html());

            foreach ($items as $item) {
                $this->assertSame('drawer', $item->action_type, "$login {$item->code}");
                // Drawer tujuan benar-benar dirender (tidak ada item mati).
                $this->assertStringContainsString("on: {$item->action_target} }", $panels, "$login {$item->code}");
            }
            $this->assertStringNotContainsString('window.open', $menu, $login);
            $this->assertStringNotContainsString('= true', $menu, $login);
        }
    }

    public function test_drawer_component_renders_head_and_close(): void
    {
        $html = Blade::render('<x-ui.drawer open="demoOpen" title="Demo" icon="fa-solid fa-star" size="xl">isi</x-ui.drawer>');

        $this->assertStringContainsString('class="drawer dr-xl"', $html);
        $this->assertStringContainsString('on: demoOpen }', $html);
        $this->assertStringContainsString('@click="demoOpen = false"', $html);
        $this->assertStringContainsString('isi', $html);
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
