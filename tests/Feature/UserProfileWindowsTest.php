<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Menguji komponen Blade v1 x-ui.drawer (kepala + tombol tutup) dan x-ui.window (kontrol geser / perkecil / perbesar / tutup).
 * Sumber hulu  : app/View/Components/Ui/Drawer.php dan Window.php.
 * Input        : Blade::render tanpa database.
 * Output       : Lulus bila markup memuat kelas dan aksi yang diharapkan.
 * Dipakai oleh : php artisan test --filter=UserProfileWindowsTest.
 * Status       : Aktif. Tes menu profil v1 (Livewire shell.user-menu / user-panels / user-configuration / user-activity) diarsipkan T10 tahap 6c.
 */
class UserProfileWindowsTest extends TestCase
{
    public function test_drawer_component_renders_head_and_close(): void
    {
        $html = Blade::render('<x-ui.drawer open="demoOpen" title="Demo" icon="fa-solid fa-star" size="xl">isi</x-ui.drawer>');

        $this->assertStringContainsString('class="drawer dr-xl"', $html);
        $this->assertStringContainsString('on: demoOpen }', $html);
        $this->assertStringContainsString('@click="demoOpen = false"', $html);
        $this->assertStringContainsString('isi', $html);
    }

    public function test_window_component_renders_controls(): void
    {
        $html = Blade::render('<x-ui.window open="demoWin" id="demo" title="Demo" icon="fa-solid fa-star">isi</x-ui.window>');

        $this->assertStringContainsString('tm-win', $html);
        $this->assertStringContainsString('toggleMin()', $html);
        $this->assertStringContainsString('toggleMax()', $html);
        $this->assertStringContainsString('close()', $html);
        $this->assertStringContainsString('dragStart($event)', $html);
        $this->assertStringContainsString('isi', $html);
    }
}
