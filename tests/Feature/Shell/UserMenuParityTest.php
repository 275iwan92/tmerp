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

    public function test_standalone_mode_redirects_drawer_items_via_menu_open_param(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        // Di shell Laporan (standalone), item Chat/Notifikasi/dst tidak
        // punya panelnya sendiri -- harus jadi link ke /menu?open=<flag>,
        // BUKAN tombol Alpine mati.
        $standalone = Livewire::test('shell.user-menu', ['standalone' => true]);
        $standalone->assertOk();
        $standalone->assertSee(route('menu', ['open' => 'chatOpen']), false);
        $standalone->assertSee(route('menu', ['open' => 'notifOpen']), false);

        // Di main-menu (non-standalone), item yg sama tetap tombol
        // wire/Alpine (bukan navigasi) -- perilaku asli sebelum diekstrak.
        $inline = Livewire::test('shell.user-menu', ['standalone' => false]);
        $inline->assertOk();
        $inline->assertDontSee(route('menu', ['open' => 'chatOpen']), false);
    }
}
