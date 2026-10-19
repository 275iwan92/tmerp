<?php

namespace Tests\Feature;

use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga tombol Back layar v2 selalu menuju grid-nya, bukan layar sebelumnya (history.back).
 * Sumber hulu  : config/devlib.php back_history; resources/views/v2/screens; packages/mockup-parts.
 * Input        : Berkas layar v2 dan konfigurasi DevLib.
 * Output       : Lulus bila back_history mati dan tak ada tombol Back berbasis riwayat browser.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class BackButtonTest extends TestCase
{
    public function test_back_history_is_off(): void
    {
        $this->assertFalse(config('devlib.back_history'));
    }

    public function test_form_back_does_not_return_to_the_pick_step(): void
    {
        foreach (['customer-invoice', 'quotation'] as $slug) {
            $this->assertDoesNotMatchRegularExpression('/label="Back" :href="\$home\.\'\?(pick|new)=1/', file_get_contents(resource_path("views/v2/screens/{$slug}.blade.php")), "{$slug}: Back harus ke grid, bukan layar pilih.");
        }
    }

    public function test_no_back_button_uses_browser_history(): void
    {
        $hits = [];
        foreach ([resource_path('views/v2'), base_path('packages/mockup-parts/resources/views')] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if (str_ends_with($f->getFilename(), '.php') && preg_match('/history\.back|url\(\)->previous\(\)/', file_get_contents($f->getPathname()))) {
                    $hits[] = $f->getPathname();
                }
            }
        }

        $this->assertSame([], $hits, 'Back harus memakai alamat grid, bukan riwayat browser.');
    }
}
