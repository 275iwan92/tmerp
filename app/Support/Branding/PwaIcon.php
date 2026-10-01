<?php

namespace App\Support\Branding;

use Illuminate\Support\Facades\Storage;

// Ikon aplikasi di HP (PWA, backlog10 10.6): dibuat dari logo perusahaan (Settings > Identifikasi, APP_LOGO_PATH) --
// logo dipaskan ke tengah kotak putih, 76% sisi (zona aman ikon "maskable" = 80%). Tanpa logo, atau logo tidak bisa dibaca
// GD (mis. SVG): tetap ikon bawaan public/images/pwa-icon-*.png. Hasil disimpan di disk public (pwa/), berganti nama
// sendiri bila logo diganti.
final class PwaIcon
{
    public const SIZES = [192, 512];

    private const FILL = 0.76;

    public static function url(int $size): string
    {
        return route('pwa.icon', $size).'?v='.substr(md5(self::file($size)), 0, 8);
    }

    // File PNG yang disajikan untuk ukuran itu.
    public static function file(int $size): string
    {
        $fallback = public_path("images/pwa-icon-{$size}.png");
        $logo = Brand::logoPath();
        if (! $logo || ! Storage::disk('public')->exists($logo)) {
            return $fallback;
        }
        $source = Storage::disk('public')->path($logo);
        $target = 'pwa/icon-'.$size.'-'.substr(md5($logo.'|'.filemtime($source)), 0, 12).'.png';
        if (! Storage::disk('public')->exists($target)) {
            $png = self::render($source, $size);
            if ($png === null) {
                return $fallback;
            }
            Storage::disk('public')->put($target, $png);
        }

        return Storage::disk('public')->path($target);
    }

    // PNG persegi $size x $size dari sebuah file gambar; null bila gambarnya tidak bisa dibaca.
    public static function render(string $sourceFile, int $size): ?string
    {
        $src = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) @file_get_contents($sourceFile)) : false;
        if (! $src) {
            return null;
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min($size * self::FILL / $w, $size * self::FILL / $h);
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];

        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $src, (int) round(($size - $nw) / 2), (int) round(($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagepng($canvas);

        return ob_get_clean() ?: null;
    }
}
