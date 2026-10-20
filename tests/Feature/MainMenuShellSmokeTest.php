<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Support\Branding\Brand;
use Livewire\Livewire;
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

        $this->get('/security?tab=groups')->assertRedirect('/v2/s/security-groups');
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

        $component = Livewire::test('pages::main-menu');
        $tree = collect($component->instance()->menuTree());

        $setup = $tree->firstWhere('code', 'SETUP');
        $this->assertNotNull($setup, 'Modul SETUP harus ada di menuTree() utk admin.');

        // 2026-10-04: ten groups fixed by the user (DevLib removed 2026-10-04, devlib.test replaces it) (migrations 2026_10_04_000010 + _000020). Setting Approval / Acting Officer
        // exist on ddt only, so Security is checked by the items both sites share.
        $children = collect($setup['children']);
        $this->assertSame(
            ['Security', 'Support & Ticket', 'Help and Support', 'Language and Localization', 'Dummy Data & Migration', 'Constants', 'Dashboard and Widgets', 'Menu', 'Identification', 'Others'],
            $children->where('menuType', 'group')->pluck('label')->values()->all()
        );
        $labels = fn (string $code) => collect($children->firstWhere('code', $code)['children'])->pluck('label')->all();
        $this->assertSame(['Accounts', 'Groups', 'Modules', 'Groups on Modules'], array_slice($labels('SET_GRP_SECURITY'), 0, 4));
        $this->assertSame(['Support Tickets'], $labels('SET_GRP_SUPPORT'));
        $this->assertSame(['Guides', 'Design Notes', 'Technical Guide', 'Help Topics', 'Help Lines', 'Assistant Questions', 'Assistant Log'], $labels('SET_GRP_HELP'));
        $this->assertSame(['Languages', 'Language', 'Localization', 'Translation Dictionary', 'Message Groups'], $labels('SET_GRP_LANG'));
        $this->assertSame(['Dummy & Legacy Data', 'Migration'], $labels('SET_GRP_DUMMY'));
        $this->assertSame(['Constants', 'Constant Categories', 'Document Number Series'], $labels('SET_GRP_CONSTANTS'));
        $this->assertSame(['Opening Dashboards', 'Opening Board Widgets', 'Opening Board Templates', 'KPI Targets', 'Daily Quotes'], $labels('SET_GRP_DASHBOARD'));
        $this->assertSame(['Menu Setup'], $labels('SET_GRP_MENU'));
        $this->assertSame(['Identification'], $labels('SET_GRP_IDENT'));

        // SET_THEME (dead v1 entry, NOT_BUILT) stays inactive inside Others, so it must not appear anywhere in the tree.
        $this->assertFalse(collect($children->firstWhere('code', 'SET_GRP_OTHERS')['children'])->contains('code', 'SET_THEME'));
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
            $component = Livewire::test('pages::main-menu');
            $component->call('setLang', 'en');

            $admin->refresh();
            $this->assertSame('en', $admin->lang_code);

            $component2 = Livewire::test('pages::main-menu');
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
