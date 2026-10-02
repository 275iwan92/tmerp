<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

// Layar v2 (Menu v2 / objek DevLib) di dalam tmerp: semua halaman v2 terbuka tanpa error. Hanya membaca data.
class V2ShellTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_v2_home_renders_layer_shell_from_sys_app_menu(): void
    {
        $this->actingAs($this->admin())->get('/v2')
            ->assertOk()->assertSee('data-dl-layer-menu', false)->assertDontSee('class="dl-topbar"', false)->assertSee('Inquiry Product')
            // Pengalih ikon Operasional | Laporan Manajemen (pilihan A, user 2026-09-25).
            ->assertSee('&quot;key&quot;:&quot;mgmt&quot;', false)->assertSee('data-lm-views', false)
            // Logo + nama aplikasi = kembali ke opening screen, semua tab ditutup (user 2026-10-02).
            ->assertSee('data-lm-home', false);
    }

    // Di luar Shell (x-dl::layer-menu berdiri sendiri) kepala panel tidak bisa diklik kecuali diminta lewat prop home.
    public function test_layer_menu_head_is_a_home_button_only_when_asked(): void
    {
        $this->assertStringNotContainsString('data-lm-home', Blade::render('<x-dl::layer-menu :items="[]" title="Zq App" />'));
        $html = Blade::render('<x-dl::layer-menu :items="[]" title="Zq App" home />');
        $this->assertMatchesRegularExpression('/class="dl-lm__head"\s+data-lm-home role="button" tabindex="0" title="[^"]+"/', $html);
    }

    public function test_v2_screens_flows_and_dictionary_open(): void
    {
        $this->actingAs($this->admin());
        foreach (['/v2/s/inquiry', '/v2/s/inquiry-new', '/v2/s/inquiry-revise', '/v2/s/inquiry-recalc', '/v2/s/prodev', '/v2/s/quotation', '/v2/flow/SALES', '/v2/flow/COSTING', '/v2/dictionary'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_menu_item_resolves_to_v1_screen_when_no_v2_target(): void
    {
        $this->actingAs($this->admin())->get('/v2/item/PROC_PO')->assertRedirect(route('pur.po.list'));
    }

    public function test_dictionary_is_admin_only(): void
    {
        $user = SecUser::where('is_admin', false)->where('activate', true)->firstOrFail();
        $this->actingAs($user)->get('/v2/dictionary')->assertForbidden();
    }
}
