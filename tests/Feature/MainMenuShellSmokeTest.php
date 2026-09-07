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

    public function test_settings_group_leaves_appear_in_sidebar_shell(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('Identifikasi');
        $response->assertSee("label: 'Language'", false);
        $response->assertSee("label: 'Localization'", false);
        $response->assertSee("label: 'Constants'", false);
    }
}
