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
            ['Identifikasi', 'Language', 'Localization', 'Constants'],
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
}
