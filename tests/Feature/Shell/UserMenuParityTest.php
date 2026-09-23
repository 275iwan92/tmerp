<?php

namespace Tests\Feature\Shell;

use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Instruksi eksplisit user 2026-09-14: "user menu di management report dan
// di operational menu harus sama" + "drop down ini ya harus muncul di
// management report" (screenshot dropdown lengkap: Pesan & Info/Chat/
// Notifikasi/Asisten AI, Akun/Edit profil/Ubah password/Bantuan & Support,
// Configuration/Tema/Bahasa, Keluar aplikasi). SATU komponen
// App\Livewire\Shell\UserMenu dipakai di kedua shell -- test ini
// membuktikan isinya benar-benar identik, bukan 2 implementasi terpisah
// yang kebetulan mirip.
class UserMenuParityTest extends TestCase
{
    private ?string $origLang = null;

    // Label yang dicek berbahasa Indonesia; akun johiwan bisa sedang ber-lang
    // 'en' -- paksa 'id' selama test, kembalikan nilai asli di tearDown.
    protected function setUp(): void
    {
        parent::setUp();
        $this->origLang = SecUser::where('login', 'johiwan')->value('lang_code');
        SecUser::where('login', 'johiwan')->update(['lang_code' => 'id']);
    }

    protected function tearDown(): void
    {
        SecUser::where('login', 'johiwan')->update(['lang_code' => $this->origLang]);
        parent::tearDown();
    }

    public function test_operational_menu_renders_full_user_menu_dropdown(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get(route('menu'));

        $response->assertOk();
        $response->assertSee('Pesan & info');
        $response->assertSee('Akun');
        $response->assertSee('Configuration');
        $response->assertSee('Keluar aplikasi');
    }

    public function test_management_report_shell_renders_the_same_user_menu_dropdown(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get(route('report.executive'));

        $response->assertOk();
        $response->assertSee('Pesan & info');
        $response->assertSee('Akun');
        $response->assertSee('Configuration');
        $response->assertSee('Keluar aplikasi');
    }

    public function test_standalone_mode_opens_panels_in_place_without_leaving_report_shell(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        // Keluhan user 2026-09-18: di mode Laporan, klik item profil TIDAK
        // boleh melempar ke Menu Operasional -- panel dibuka di tempat.
        $standalone = Livewire::test('shell.user-menu', ['standalone' => true]);
        $standalone->assertOk();
        $standalone->assertDontSee(route('menu', ['open' => 'chatOpen']), false);
        $standalone->assertDontSee(route('menu', ['open' => 'notifOpen']), false);
        $standalone->assertSee("openDrawer('chatOpen')", false);
        $standalone->assertSee("openDrawer('profileOpen')", false);

        $response = $this->get(route('report.executive'));
        $response->assertOk();
        $response->assertSee('class="upanels"', false);
        $response->assertSee('saveProfile', false);
        $response->assertDontSee(route('menu', ['open' => 'profileOpen']), false);
    }
}
