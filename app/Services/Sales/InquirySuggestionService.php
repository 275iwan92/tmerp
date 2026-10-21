<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sdi\IqpPartFinishing;
use App\Models\Sdi\IqpPartMaterial;
use App\Support\V2\Data\Sales;
use Illuminate\Support\Facades\Storage;

/*
 * REMARK
 * Tujuan       : Saran untuk Sales saat mengisi satu Part Inquiry: notasi warna dan ukuran flat dari artwork, bahan dan finishing dari histori Inquiry bertipe sama. Engine menyarankan, Sales memutuskan (A.7); tidak ada yang tersimpan di sini.
 * Sumber hulu  : Berkas artwork (jpg/png), tm2sdi.iqp_part (dieline_spec.type_code), iqp_part_material, iqp_part_finishing, tm2mst.mat_category.
 * Input        : Kode tipe produk, path artwork (disk public) atau berkas unggahan sementara.
 * Output       : Daftar saran [kind, tone, text, action, apply] untuk strip di x-mp::sales.inquiry-part; tombol Pakai hanya mengisi kotak form, penyimpanan tetap lewat Save.
 * Dipakai oleh : routes/v2.php (POST v2/inquiry/suggest) -> InquiryForm.js.
 * Status       : Aktif (T10 tahap 4, 2026-10-21). Aturan dan rumus dipindah apa adanya dari pages/inquiry/form (v1, diarsipkan).
 */
final class InquirySuggestionService
{
    public function forPart(string $typeCode, ?string $artworkFile): array
    {
        return array_values(array_filter([
            $this->size($artworkFile),
            $this->material($typeCode),
            $this->color($artworkFile),
            $this->finishing($typeCode),
        ]));
    }

    public function storedArtwork(?string $path): ?string
    {
        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : null;
    }

    private function size(?string $file): ?array
    {
        $info = $file && is_file($file) ? @getimagesize($file) : false;
        if (! $info) {
            return null;
        }
        $dpi = match ($info[2]) {
            IMAGETYPE_JPEG => $this->jpegDpi($file),
            IMAGETYPE_PNG => $this->pngDpi($file),
            default => null,
        };
        if (! $dpi || $dpi['dpi_x'] <= 0 || $dpi['dpi_y'] <= 0) {
            return null;
        }
        $w = round(($info[0] / $dpi['dpi_x']) * 25.4, 1);
        $h = round(($info[1] / $dpi['dpi_y']) * 25.4, 1);
        $dpiX = (int) round($dpi['dpi_x']);
        $warn = in_array($dpiX, [72, 96], true)
            ? tf(':dpi dpi is usually just a screen or software default, NOT the real print scale — most likely inaccurate, do not use it right away.', ['dpi' => $dpiX])
            : t('Still check it manually before confirming to the customer.');

        return ['kind' => 'size', 'tone' => 'warn', 'action' => t('Use as Flat W/H'), 'apply' => ['w' => $w, 'h' => $h],
            'text' => tf('📐 Size read from the artwork DPI metadata (:x×:y dpi): :w × :h mm — assuming this is the printed flat blank, not the outer box dimensions.', ['x' => $dpiX, 'y' => (int) round($dpi['dpi_y']), 'w' => $w, 'h' => $h]).' '.$warn];
    }

    private function material(string $typeCode): ?array
    {
        if ($typeCode === '') {
            return null;
        }
        $base = fn () => IqpPartMaterial::query()->join('iqp_part', 'iqp_part.id_part', '=', 'iqp_part_material.part_id')
            ->where('iqp_part.dieline_spec->type_code', $typeCode)->whereNotNull('iqp_part_material.category_id');
        $top = $base()->selectRaw('iqp_part_material.category_id, iqp_part_material.rawmat_name, count(*) as cnt')
            ->groupBy('iqp_part_material.category_id', 'iqp_part_material.rawmat_name')->orderByDesc('cnt')->first();
        if (! $top) {
            return null;
        }
        $total = $base()->count();

        return ['kind' => 'material', 'tone' => 'info', 'action' => t('Use suggestion'), 'apply' => Sales::materialSlot((int) $top->category_id),
            'text' => tf('💡 Material suggestion from history: :name (used in :pct% of the earlier Inquiries of this product type)', ['name' => $top->rawmat_name, 'pct' => $this->pct((int) $top->cnt, $total)])];
    }

    private function color(?string $file): ?array
    {
        $info = $file && is_file($file) ? @getimagesize($file) : false;
        $img = $info ? match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            default => null,
        } : null;
        if (! $img) {
            return null;
        }
        // Downsample ke grid kecil: cukup untuk gambaran warna, lebih cepat, noise kompresi JPEG teredam.
        $sample = imagecreatetruecolor(40, 40);
        imagecopyresampled($sample, $img, 0, 0, 0, 0, 40, 40, imagesx($img), imagesy($img));
        imagedestroy($img);
        $colors = [];
        $gray = true;
        for ($y = 0; $y < 40; $y++) {
            for ($x = 0; $x < 40; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                [$r, $g, $b] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
                if (abs($r - $g) > 12 || abs($g - $b) > 12 || abs($r - $b) > 12) {
                    $gray = false;
                }
                $colors[intdiv($r, 32).'-'.intdiv($g, 32).'-'.intdiv($b, 32)] = true; // kuantisasi kelipatan 32
            }
        }
        imagedestroy($sample);
        $n = count($colors);
        $code = match (true) {
            $gray && $n <= 3 => '1/0',
            ! $gray && $n <= 4 => '2/0',
            ! $gray && $n <= 10 => '4/0',
            default => '4/4',
        };

        return ['kind' => 'color', 'tone' => 'info', 'action' => t('Use suggestion'), 'apply' => ['code' => $code],
            'text' => tf('💡 Colour notation suggestion from the artwork analysis: :code (:n colours detected:bw) — rough estimate, check before using.', ['code' => $code, 'n' => $n, 'bw' => $gray ? ', '.t('black and white') : ''])];
    }

    private function finishing(string $typeCode): ?array
    {
        if ($typeCode === '') {
            return null;
        }
        $base = fn () => IqpPartFinishing::query()->join('iqp_part', 'iqp_part.id_part', '=', 'iqp_part_finishing.part_id')->where('iqp_part.dieline_spec->type_code', $typeCode);
        $top = $base()->selectRaw('iqp_part_finishing.finishing_type_id, iqp_part_finishing.finishing_name, count(*) as cnt')
            ->groupBy('iqp_part_finishing.finishing_type_id', 'iqp_part_finishing.finishing_name')->orderByDesc('cnt')->first();
        if (! $top) {
            return null;
        }

        return ['kind' => 'finishing', 'tone' => 'warn', 'action' => t('Add to Finishing'), 'apply' => ['finishing_type_id' => (int) $top->finishing_type_id],
            'text' => tf('📋 Finishing suggestion from history: :name (used in :pct% of the earlier Inquiries of this product type).', ['name' => $top->finishing_name, 'pct' => $this->pct((int) $top->cnt, $base()->count())])];
    }

    private function pct(int $part, int $total): int
    {
        return $total > 0 ? (int) round($part / $total * 100) : 0;
    }

    // JFIF APP0 (kebanyakan export Illustrator/Photoshop menyimpan density di sini): units 1 = dots/inch, 2 = dots/cm.
    private function jpegDpi(string $path): ?array
    {
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return null;
        }
        try {
            if (fread($fh, 2) !== "\xFF\xD8") {
                return null;
            }
            while (! feof($fh)) {
                $marker = fread($fh, 2);
                if (strlen($marker) < 2 || $marker[0] !== "\xFF" || $marker === "\xFF\xDA") {
                    break;
                }
                $lenRaw = fread($fh, 2);
                if (strlen($lenRaw) < 2) {
                    break;
                }
                $segLen = unpack('n', $lenRaw)[1];
                if ($marker === "\xFF\xE0" && $segLen >= 14) {
                    $payload = fread($fh, $segLen - 2);
                    if (substr($payload, 0, 5) === "JFIF\0") {
                        $x = unpack('n', substr($payload, 8, 2))[1];
                        $y = unpack('n', substr($payload, 10, 2))[1];

                        return match (ord($payload[7])) {
                            1 => ['dpi_x' => $x, 'dpi_y' => $y],
                            2 => ['dpi_x' => $x * 2.54, 'dpi_y' => $y * 2.54],
                            default => null,
                        };
                    }

                    continue;
                }
                fseek($fh, $segLen - 2, SEEK_CUR);
            }

            return null;
        } finally {
            fclose($fh);
        }
    }

    // Chunk pHYs: unit specifier 1 = meter; selain itu hanya rasio (bukan skala nyata) jadi diabaikan.
    private function pngDpi(string $path): ?array
    {
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return null;
        }
        try {
            if (fread($fh, 8) !== "\x89PNG\r\n\x1a\n") {
                return null;
            }
            while (! feof($fh)) {
                $lenRaw = fread($fh, 4);
                if (strlen($lenRaw) < 4) {
                    break;
                }
                $len = unpack('N', $lenRaw)[1];
                $type = fread($fh, 4);
                if ($type === 'pHYs' && $len === 9) {
                    $chunk = fread($fh, 9);
                    $x = unpack('N', substr($chunk, 0, 4))[1];
                    $y = unpack('N', substr($chunk, 4, 4))[1];

                    return ord($chunk[8]) === 1 && $x > 0 && $y > 0 ? ['dpi_x' => $x * 0.0254, 'dpi_y' => $y * 0.0254] : null;
                }
                if ($type === 'IDAT') {
                    break;
                }
                fseek($fh, $len + 4, SEEK_CUR);
            }

            return null;
        } finally {
            fclose($fh);
        }
    }
}
