<?php

namespace App\Services\ProDev;

use Imagick;

// Analisa warna artwork DI SERVER, termasuk file PDF / AI / EPS (Imagick +
// Ghostscript dipasang 2026-09-18, BACKLOG §19.4). Hasilnya bentuknya SAMA
// dengan analisa di browser (channels C/M/Y/K + daftar warna dominan), jadi
// dipakai Ink Configuration & BOM tanpa perbedaan jalur.
//
// File vektor juga dibuatkan pratinjau PNG, karena <image> di canvas ProDev
// tidak bisa menggambar PDF/AI langsung.
class ArtworkAnalyzer
{
    public const VECTOR = ['pdf', 'ai', 'eps'];

    private const MAX_PX = 360;

    public static function available(): bool
    {
        return extension_loaded('imagick');
    }

    public function supports(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', ...self::VECTOR], true);
    }

    /**
     * @return array{channels: array, colors: array, source: string, px: int, preview: ?string}|null
     */
    public function analyze(string $absPath, ?string $previewPath = null): ?array
    {
        if (! is_file($absPath) || ! self::available()) {
            return null;
        }

        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $im = new Imagick;

        try {
            if (in_array($ext, self::VECTOR, true)) {
                // 150 dpi cukup untuk mengukur luas warna, tidak memberatkan.
                $im->setResolution(150, 150);
                $im->readImage($absPath.'[0]');
            } else {
                $im->readImage($absPath);
            }

            $im->setImageBackgroundColor('white');
            $im = $im->flattenImages();

            if ($previewPath) {
                $preview = clone $im;
                $preview->setImageFormat('png');
                $preview->thumbnailImage(1200, 1200, true);
                @mkdir(dirname($previewPath), 0755, true);
                $preview->writeImage($previewPath);
                $preview->clear();
            }

            $im->thumbnailImage(self::MAX_PX, self::MAX_PX, true);
            $w = $im->getImageWidth();
            $h = $im->getImageHeight();
            $pixels = $im->exportImagePixels(0, 0, $w, $h, 'RGB', Imagick::PIXEL_CHAR);
        } catch (\Throwable $e) {
            return null;
        } finally {
            $im->clear();
        }

        return $this->measure($pixels, $w * $h) + [
            'source' => 'server:'.basename($absPath).':'.($previewPath ? 'v' : 'i'),
            'px' => $w * $h,
            'preview' => $previewPath && is_file($previewPath) ? $previewPath : null,
        ];
    }

    /**
     * Tutupan tinta per channel (persen terhadap seluruh bidang) + warna
     * dominan. Rumusnya sama dengan analyzePixels() di layar ProDev.
     */
    private function measure(array $rgb, int $total): array
    {
        $ch = ['C' => 0.0, 'M' => 0.0, 'Y' => 0.0, 'K' => 0.0];
        $groups = [];
        $n = count($rgb);

        for ($i = 0; $i + 2 < $n; $i += 3) {
            $r = $rgb[$i] / 255;
            $g = $rgb[$i + 1] / 255;
            $b = $rgb[$i + 2] / 255;
            $k = 1 - max($r, $g, $b);

            if ($k < 1) {
                $ch['C'] += (1 - $r - $k) / (1 - $k);
                $ch['M'] += (1 - $g - $k) / (1 - $k);
                $ch['Y'] += (1 - $b - $k) / (1 - $k);
            }
            $ch['K'] += $k;

            $name = $this->colorName($r, $g, $b);
            $groups[$name] ??= ['n' => 0, 'r' => 0.0, 'g' => 0.0, 'b' => 0.0];
            $groups[$name]['n']++;
            $groups[$name]['r'] += $r;
            $groups[$name]['g'] += $g;
            $groups[$name]['b'] += $b;
        }

        $total = max(1, $total);
        $hex = fn ($v) => str_pad(dechex((int) round($v * 255)), 2, '0', STR_PAD_LEFT);

        $colors = collect($groups)
            ->map(fn ($g, $name) => [
                'name' => $name,
                'hex' => '#'.$hex($g['r'] / $g['n']).$hex($g['g'] / $g['n']).$hex($g['b'] / $g['n']),
                'pct' => round($g['n'] / $total * 100, 2),
            ])
            ->filter(fn ($c) => $c['pct'] >= 0.3)
            ->sortByDesc('pct')
            ->take(12)
            ->values()->all();

        return [
            'channels' => collect($ch)->map(fn ($v) => round($v / $total * 100, 2))->all(),
            'colors' => $colors,
        ];
    }

    // Kroma (selisih max-min), bukan saturasi HSL: saturasi HSL membesar di
    // warna sangat terang sehingga #fafbff terbaca "biru".
    private function colorName(float $r, float $g, float $b): string
    {
        $mx = max($r, $g, $b);
        $mn = min($r, $g, $b);
        $l = ($mx + $mn) / 2;
        $d = $mx - $mn;
        $s = $d == 0 ? 0 : $d / (1 - abs(2 * $l - 1));

        if ($l > .86 && $d < .12) {
            return 'Putih';
        }
        if ($l < .13) {
            return 'Hitam';
        }
        if ($d < .08 || $s < .12) {
            return 'Abu-abu';
        }

        $hue = match ($mx) {
            $r => fmod(($g - $b) / $d, 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };
        $hue = fmod($hue * 60 + 360, 360);

        return match (true) {
            $hue >= 30 && $hue < 55 && $s < .8 && $l < .62 => 'Emas',
            $hue < 15 || $hue >= 345 => $l < .3 ? 'Merah Tua' : 'Merah',
            $hue < 40 => $l < .4 ? 'Coklat' : 'Oranye',
            $hue < 70 => 'Kuning',
            $hue < 165 => 'Hijau',
            $hue < 200 => 'Tosca',
            $hue < 255 => $l < .3 ? 'Biru Tua' : 'Biru',
            $hue < 290 => 'Ungu',
            default => 'Pink',
        };
    }
}
