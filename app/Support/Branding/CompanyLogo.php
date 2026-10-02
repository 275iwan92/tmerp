<?php

namespace App\Support\Branding;

use App\Models\Sys\SysConstant;
use Illuminate\Support\Facades\Storage;

// Logo PERUSAHAAN (Telasih Makmur) -- terpisah dari logo & nama APLIKASI (Brand::logoPath / APP_LOGO_PATH), yang tidak
// boleh diganti logo perusahaan (user 2026-10-02). Dipakai di kop cetakan. Tiga versi, path di tm2sys.sys_constant:
//   color   = COMPANY_LOGO_PATH          berwarna, tulisan hitam, latar putih (hemat tinta) -> cetakan versi warna
//   bw      = COMPANY_LOGO_BW_PATH       hitam-putih, DIBUAT SENDIRI dari versi warna      -> cetakan versi hitam-putih
//   inverse = COMPANY_LOGO_INVERSE_PATH  tulisan putih                                     -> di atas latar gelap
// File di disk public (branding/), diisi lewat Settings > Identifikasi.
final class CompanyLogo
{
    public const KEYS = ['color' => 'COMPANY_LOGO_PATH', 'bw' => 'COMPANY_LOGO_BW_PATH', 'inverse' => 'COMPANY_LOGO_INVERSE_PATH'];

    private const MAX_WIDTH = 1600;

    // Di atas ini GD butuh memori terlalu besar: file disimpan apa adanya, tanpa versi hitam-putih.
    private const MAX_PIXELS = 16_000_000;

    public static function path(string $variant = 'color'): ?string
    {
        $path = SysConstant::text(self::KEYS[$variant]);

        return $path && Storage::disk('public')->exists($path) ? $path : null;
    }

    // Versi hitam-putih belum ada (logo terlalu besar untuk diolah) -> versi warna.
    public static function url(string $variant = 'color'): ?string
    {
        $path = self::path($variant) ?? ($variant === 'bw' ? self::path('color') : null);

        return $path ? asset('storage/'.$path).'?v='.Storage::disk('public')->lastModified($path) : null;
    }

    // Simpan logo baru untuk 'color' atau 'inverse'. 'color' sekaligus membuat ulang versi hitam-putih.
    // File lama TIDAK dihapus: disk public dipakai bersama tmerp.test dan mockup.test, sedangkan path-nya dicatat di dua
    // database berbeda (tm2sys / ddtsys) -- menghapus di satu situs memutus logo situs lainnya.
    public static function store(string $variant, string $sourceFile, string $extension, ?string $by = null): void
    {
        self::remove($variant, $by);
        $stamp = substr(md5_file($sourceFile), 0, 8);
        $base = 'branding/company-logo'.($variant === 'inverse' ? '-inverse' : '').'-'.$stamp;
        $image = self::read($sourceFile);

        if (! $image) {
            Storage::disk('public')->put($base.'.'.$extension, file_get_contents($sourceFile));
            self::set(self::KEYS[$variant], $base.'.'.$extension, $by);

            return;
        }
        Storage::disk('public')->put($base.'.png', self::png($image));
        self::set(self::KEYS[$variant], $base.'.png', $by);
        if ($variant === 'color') {
            imagefilter($image, IMG_FILTER_GRAYSCALE);
            Storage::disk('public')->put('branding/company-logo-bw-'.$stamp.'.png', self::png($image));
            self::set(self::KEYS['bw'], 'branding/company-logo-bw-'.$stamp.'.png', $by);
        }
    }

    public static function remove(string $variant, ?string $by = null): void
    {
        foreach ($variant === 'color' ? ['color', 'bw'] : [$variant] as $v) {
            self::set(self::KEYS[$v], null, $by);
        }
    }

    // Gambar GD selebar paling banyak MAX_WIDTH, transparansi dijaga; null bila tidak bisa / terlalu besar diolah.
    private static function read(string $file)
    {
        [$w, $h] = @getimagesize($file) ?: [0, 0];
        if (! $w || $w * $h > self::MAX_PIXELS || ! ($src = @imagecreatefromstring((string) file_get_contents($file)))) {
            return null;
        }
        $nw = min($w, self::MAX_WIDTH);
        $nh = max(1, (int) round($h * $nw / $w));
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $out;
    }

    private static function png($image): string
    {
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }

    private static function set(string $key, ?string $value, ?string $by): void
    {
        SysConstant::updateOrCreate(['constant_key' => $key], ['constant_text' => $value, 'updated_by' => $by]);
    }
}
