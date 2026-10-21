<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use App\Support\V2\AppMenu;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Menjaga alamat lama menuju Menu v2: /menu dan /admin/* (Filament dibuang) ke /v2, rute Setup / Security lama ke layar v2, dan pohon menu Setup (sepuluh grup tetap) yang disusun AppMenu dari sys_app_menu.
 * Sumber hulu  : routes/web.php (/menu, redirect /security, /setup/*), App\Support\V2\AppMenu::modules, tm2sys.sys_app_menu (ddtsys di mockup).
 * Input        : User johiwan (administrator), hanya dibaca.
 * Output       : Lulus bila pengalihan dan susunan grup Setup sesuai.
 * Dipakai oleh : php artisan test --filter=MenuRedirectTest.
 * Status       : Aktif (T10 tahap 6c, 2026-10-21). Menggantikan MainMenuShellSmokeTest (shell v1 pages::main-menu diarsipkan).
 */
class MenuRedirectTest extends TestCase
{
    private function admin(): SecUser
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        return $admin;
    }

    public function test_menu_url_goes_to_v2(): void
    {
        $this->admin();

        $this->get('/menu')->assertRedirect('/v2');
    }

    // Panel Filament /admin dibuang (T10 tahap 6e): semua alamat lamanya ke Menu v2.
    public function test_old_filament_admin_urls_go_to_v2(): void
    {
        foreach (['/admin', '/admin/hrm/emp-employees', '/admin/hrm/org-positions/3/edit', '/admin/profile', '/admin/login'] as $url) {
            $this->get($url)->assertRedirect('/v2');
        }
    }

    public function test_old_security_and_setup_urls_open_v2_screens(): void
    {
        $this->admin();

        $this->get('/security?tab=groups')->assertRedirect('/v2/s/security-groups');
        $this->get('/setup/identification')->assertRedirect('/v2/s/setup-identification');
        $this->get('/setup/language')->assertRedirect('/v2/s/languages');
        $this->get('/setup/localization')->assertRedirect('/v2/s/localization');
        $this->get('/setup/constants')->assertRedirect('/v2/s/setup-constants');
    }

    // Sepuluh grup Setup ditetapkan user 2026-10-04; isi tiap grup = anak aktif di sys_app_menu, SET_THEME (entri v1 mati) tetap tak tampil.
    public function test_setup_module_has_the_ten_fixed_groups_with_their_active_items(): void
    {
        $setup = collect(AppMenu::modules($this->admin()))->firstWhere('code', 'SETUP');
        $this->assertNotNull($setup, 'Modul SETUP harus ada di menu v2 untuk admin.');

        $groups = collect($setup['children'])->filter(fn ($c) => isset($c['items']));
        $this->assertSame(
            ['SET_GRP_SECURITY', 'SET_GRP_SUPPORT', 'SET_GRP_HELP', 'SET_GRP_LANG', 'SET_GRP_DUMMY', 'SET_GRP_CONSTANTS', 'SET_GRP_DASHBOARD', 'SET_GRP_MENU', 'SET_GRP_IDENT', 'SET_GRP_OTHERS'],
            $groups->pluck('code')->values()->all()
        );

        foreach ($groups as $g) {
            $parent = SysAppMenu::where('code', $g['code'])->value('id');
            $active = SysAppMenu::where('parent_id', $parent)->where('activate', true)->where('menu_type', '!=', 'heading')
                ->when(SysAppMenu::query()->getConnection()->getSchemaBuilder()->hasColumn('sys_app_menu', 'hide_in_menu'), fn ($q) => $q->where('hide_in_menu', false))->pluck('code')->all();
            $shown = collect($g['items'])->pluck('code')->all();
            $this->assertEmpty(array_diff($shown, $active), "{$g['code']}: item tampil yang tidak aktif di sys_app_menu");
        }
        $this->assertNotContains('SET_THEME', $groups->flatMap(fn ($g) => collect($g['items'])->pluck('code'))->all());
    }
}
