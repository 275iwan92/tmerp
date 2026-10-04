<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

// Side Screen (user 2026-10-04): Help & support dan Edit profile = jendela layar penuh objek DevLib x-dl::side-screen
// (bisa mengambang, dipindah, diubah ukurannya), isinya rel bagian vertikal x-dl::side-screen.layout.
class SideScreenTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_shell_hosts_help_and_profile_as_side_screens(): void
    {
        $html = $this->actingAs($this->admin())->get('/v2')->assertOk()->getContent();
        $this->assertStringContainsString('data-dl-side-screen="help"', $html);
        $this->assertStringContainsString('data-dl-side-screen="edit"', $html);
        $this->assertStringContainsString('data-dl-ss-float', $html);
        $this->assertStringContainsString('data-dl-ss-rs="se"', $html);
        $this->assertStringNotContainsString('dl-drawer--wide', $html);
    }

    public function test_help_and_profile_pages_use_the_sections_layout(): void
    {
        $this->actingAs($this->admin());
        foreach (['/v2/help' => 'data-dl-ss-nav="flow"', '/v2/help?tab=tickets' => 'data-dl-ss-nav="tickets"', '/v2/profile' => 'data-dl-ss-nav="security"'] as $url => $needle) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('data-dl-ss-layout', $html, $url);
            $this->assertStringContainsString($needle, $html, $url);
            $this->assertStringNotContainsString('class="dl-cat__nav"', $html, $url.' (breadcrumb lives in the section head)');
        }
    }

    public function test_component_renders_window_chrome_and_sticky_foot(): void
    {
        $html = Blade::render('<x-dl::side-screen id="zq" title="Zq" subtitle="Sub" icon="fa-circle-question" url="/zq" />');
        $this->assertStringContainsString('name="dl-wide"', $html);
        $this->assertStringContainsString('data-dl-ss-url="/zq"', $html);
        $this->assertStringContainsString('data-dl-ss-search', $html);
        $this->assertStringContainsString('data-dl-close', $html);

        $html = Blade::render('<x-dl::side-screen.layout :sections="[\'a\' => [\'Alpha\', \'fa-user\', 3], \'b\' => [\'Beta\', \'fa-book\']]" :groups="[\'b\' => \'Second\']" :hints="[\'a\' => \'Hint A\']"><x-slot:a>A<x-dl::side-screen.foot hint="H">F</x-dl::side-screen.foot></x-slot:a><x-slot:b>B</x-slot:b></x-dl::side-screen.layout>');
        $this->assertStringContainsString('data-dl-ss-nav="a"', $html);
        $this->assertStringContainsString('dl-ssl__n is-alert">3<', $html);
        $this->assertStringContainsString('dl-ssl__cap">Second<', $html);
        $this->assertStringContainsString('data-dl-ss-sec-hint>Hint A<', $html);
        $this->assertStringContainsString('dl-ssl__foot-hint">H<', $html);
    }

    // Demo katalog devlib.test (Part 2 > Containers & Navigation > Side Screen) harus tetap bisa dirender.
    public function test_devlib_catalog_demo_renders(): void
    {
        $html = view()->file(base_path('devlib/resources/views/demos/side-screen.blade.php'))->render();
        $this->assertStringContainsString('data-dl-side-screen="demo-help"', $html);
        $this->assertStringContainsString('data-dl-ss-nav="tickets"', $html);
    }

    // Chat, AI Assistant, Notifications (user 2026-10-04): laci sempit dengan dua mode -- Maximize (satu layar penuh) / Restore.
    public function test_narrow_drawers_and_drawer_object_offer_maximize(): void
    {
        $html = $this->actingAs($this->admin())->get('/v2')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, 'data-dl-dr-max'), 'chat + ai + notif');

        $this->assertStringContainsString('data-dl-dr-max', Blade::render('<x-dl::drawer id="zq" title="Zq" maximize>x</x-dl::drawer>'));
        $this->assertStringNotContainsString('data-dl-dr-max', Blade::render('<x-dl::drawer id="zq" title="Zq">x</x-dl::drawer>'));
    }
}
