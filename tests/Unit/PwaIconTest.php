<?php

namespace Tests\Unit;

use App\Support\Branding\PwaIcon;
use PHPUnit\Framework\TestCase;

// Ikon PWA dari logo: persegi, logo di tengah dalam zona aman, latar putih. Tidak menyentuh database (file sementara saja).
class PwaIconTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'is_file'));
        parent::tearDown();
    }

    private function logo(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
        $this->files[] = $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng($img, $path);

        return $path;
    }

    public function test_wide_logo_becomes_a_square_icon_inside_the_safe_zone(): void
    {
        $icon = imagecreatefromstring(PwaIcon::render($this->logo(400, 100), 192));

        $this->assertSame([192, 192], [imagesx($icon), imagesy($icon)]);
        $rgb = fn (int $x, int $y) => array_values(array_slice(imagecolorsforindex($icon, imagecolorat($icon, $x, $y)), 0, 3));
        $this->assertSame([255, 255, 255], $rgb(2, 2), 'corner stays white');
        $this->assertSame([255, 255, 255], $rgb(96, 20), 'above a wide logo stays white');
        $this->assertSame([200, 30, 30], $rgb(96, 96), 'logo is in the centre');
        // 76% dari 192 = 146 px lebar -> tepi kiri logo sekitar x = 23; x = 10 masih putih (di luar zona aman tidak ada logo).
        $this->assertSame([255, 255, 255], $rgb(10, 96));
    }

    public function test_unreadable_logo_gives_null(): void
    {
        $this->files[] = $path = tempnam(sys_get_temp_dir(), 'logo').'.svg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->assertNull(PwaIcon::render($path, 192));
    }
}
