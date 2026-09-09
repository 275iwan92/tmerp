<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Support\Branding\Brand;
use Tests\TestCase;

class MainMenuShellSmokeTest extends TestCase
{
    public function test_admin_sees_full_menu_shell(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get('/menu');

        $response->assertOk();
        // FIX 2026-09-03: brand name dulu literal "TELASIH MAKMUR" hardcode di
        // <title>, sekarang configurable via Brand::name() (docs/configurationstandard.md,
        // dibangun sesi sebelumnya) -- default "ERP TM", bisa diganti admin.
        // Assert dinamis, bukan string usang.
        $response->assertSee(Brand::name());
        // Modul dari sys_app_menu harus muncul di mega menu (admin bypass semua izin).
        $response->assertSee('Sales');
        $response->assertSee('Procurement');
        $response->assertSee('Setup', false);
    }

    public function test_sales_staff_sees_only_permitted_modules(): void
    {
        $sales = SecUser::where('login', 'rina')->firstOrFail();
        $this->actingAs($sales);

        $response = $this->get('/menu');

        $response->assertOk();
        // rina (SALES_STAFF) hanya di-grant INQUIRY -> module SALES saja yang boleh tampil.
        $response->assertSee('Sales');
        $response->assertDontSee('Procurement');
        $response->assertDontSee('>Setup<', false);
    }

    public function test_setup_menu_item_opens_security_index_with_tab(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/security?tab=groups')->assertOk()->assertSee('Grup');
    }

    // Settings dipecah 2026-09-07 dari 1 layar gado-gado (setup.configuration)
    // jadi 4 route+layar sendiri -- docs/configurationstandard.md.
    public function test_settings_submenus_each_open_own_screen(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/setup/identification')->assertOk()->assertSee('Identitas Aplikasi');
        $this->get('/setup/language')->assertOk()->assertSee('Language');
        $this->get('/setup/localization')->assertOk()->assertSee('Terjemahan Teks UI');
        $this->get('/setup/constants')->assertOk()->assertSee('Constants');
    }

    // "Library Component for UI/UX" (2026-09-09) -- showcase resources/views/
    // components/form/*.blade.php baru (16 komponen, docs/mockups/UIUX_components_part1.html
    // + mockup_UIUX_calendar.html versi ringkas), menu SET_UILIB di grup Settings.
    public function test_component_library_screen_opens_and_is_in_settings_menu(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/setup/component-library')->assertOk()->assertSee('Library Component for UI/UX');

        $component = \Livewire\Livewire::test('pages::main-menu');
        $tree = collect($component->instance()->menuTree());
        $setup = $tree->firstWhere('code', 'SETUP');
        $settingsGroup = collect($setup['children'])->firstWhere('code', 'SET_GRP_SETTINGS');

        $this->assertTrue(collect($settingsGroup['children'])->contains('code', 'SET_UILIB'));
    }

    // Tab Calendar (2026-09-09) -- replika docs/mockups/mockup_UIUX_calendar.html
    // (bukan lagi versi ringkas x-form.mini-calendar) -- view Day/Week/Month,
    // drag-drop (calMoveEvent), CRUD event (calCreateNew/calSaveEdit/calDeleteEvent).
    public function test_calendar_tab_views_and_event_crud(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $c = \Livewire\Livewire::test('pages::setup.component-library');
        $c->assertOk()->assertSee('Event Calendar');

        foreach (['month', 'day', 'week'] as $view) {
            $c->call('calSetView', $view)->assertOk();
        }

        $beforeCount = count($c->get('calEvents'));
        $c->call('calCreateNew');
        $newId = $c->get('calEditingId');
        $this->assertNotNull($newId);
        $this->assertCount($beforeCount + 1, $c->get('calEvents'));

        $c->set('calEditTitle', 'Rapat Demo Test')->call('calSaveEdit');
        $this->assertNull($c->get('calEditingId'));
        $saved = collect($c->get('calEvents'))->firstWhere('id', $newId);
        $this->assertSame('Rapat Demo Test', $saved['title']);

        $targetDate = now()->addDays(5)->toDateString();
        $c->call('calMoveEvent', $newId, $targetDate);
        $moved = collect($c->get('calEvents'))->firstWhere('id', $newId);
        $this->assertStringStartsWith($targetDate, $moved['start']);

        $c->call('calDeleteEvent', $newId);
        $this->assertCount($beforeCount, $c->get('calEvents'));
    }

    // 2026-09-07: footer "Settings"/"Security" dulu 2 array Alpine literal
    // hardcode di main-menu.blade.php -- sekarang 100% data-driven dari
    // sys_app_menu (SET_GRP_SETTINGS/SET_GRP_SECURITY, migration
    // 2026_09_07_000004) supaya gampang dikonfigurasi lewat tabel, bukan
    // edit Blade. Assert LANGSUNG ke computed menuTree() (bukan scraping
    // HTML/JSON mentah -- rapuh terhadap perubahan serialisasi Alpine).
    public function test_settings_group_leaves_appear_in_sidebar_shell(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $component = \Livewire\Livewire::test('pages::main-menu');
        $tree = collect($component->instance()->menuTree());

        $setup = $tree->firstWhere('code', 'SETUP');
        $this->assertNotNull($setup, 'Modul SETUP harus ada di menuTree() utk admin.');

        $children = collect($setup['children']);
        $settingsGroup = $children->firstWhere('code', 'SET_GRP_SETTINGS');
        $securityGroup = $children->firstWhere('code', 'SET_GRP_SECURITY');
        $this->assertNotNull($settingsGroup);
        $this->assertNotNull($securityGroup);
        $this->assertSame('group', $settingsGroup['menuType']);
        $this->assertSame('group', $securityGroup['menuType']);

        $this->assertSame(
            ['Identifikasi', 'Language', 'Localization', 'Constants', 'Library Component for UI/UX'],
            collect($settingsGroup['children'])->pluck('label')->all()
        );
        $this->assertSame(
            ['Account', 'Group', 'Group per Submodule', 'Migration', 'Support Tickets'],
            collect($securityGroup['children'])->pluck('label')->all()
        );

        // SET_THEME/SET_MENU (dead entries lama, NOT_BUILT tanpa route nyata)
        // sengaja dinonaktifkan (migration 2026_09_07_000005) supaya tidak
        // ikut nongol di footer sbg efek samping data-driven-nya SETUP.
        $this->assertFalse($children->contains('code', 'SET_THEME'));
        $this->assertFalse($children->contains('code', 'SET_MENU'));
    }

    // Kerangka i18n app-wide (2026-09-07, App\Support\Localization\Translator)
    // -- picker Bahasa di dropdown profil dulu dummy (cuma toast, tidak
    // menyimpan/mengubah apapun). Sekarang persist ke sec_user.lang_code +
    // beberapa string chrome shell paling dasar (Tema/Bahasa/Logout/dst)
    // sungguh berubah. TIDAK ada RefreshDatabase di project ini (test jalan
    // atas DB real) -- WAJIB revert lang_code di finally, jangan tinggalkan
    // akun demo dalam state 'en'.
    public function test_setting_language_persists_and_switches_shell_chrome(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);
        $originalLang = $admin->lang_code;

        try {
            $component = \Livewire\Livewire::test('pages::main-menu');
            $component->call('setLang', 'en');

            $admin->refresh();
            $this->assertSame('en', $admin->lang_code);

            $component2 = \Livewire\Livewire::test('pages::main-menu');
            $component2->assertSee('Theme');
            $component2->assertSee('Log out');

            // Kode bahasa tak dikenal (bukan dari sys_languages) HARUS ditolak.
            $component2->call('setLang', 'zz-INVALID');
            $admin->refresh();
            $this->assertSame('en', $admin->lang_code);
        } finally {
            $admin->update(['lang_code' => $originalLang]);
        }
    }
}
