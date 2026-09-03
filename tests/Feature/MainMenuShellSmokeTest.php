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
}
