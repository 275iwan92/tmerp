<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

// Menarik gambar Master Card legacy ScriptCase ke Master Produk V2.
//
// Rantainya: mst_card.preview_print (nama file) -> prod_card_legacy -> jembatan
// prod_product_component_legacy -> prod_product. Tidak ada jalur langsung
// item->gambar yang bisa dipakai: mst_item.preview_img memang terisi 825 baris
// tapi isinya nama file PDF yang tidak ikut ter-copy ke folder img (cuma 2 dari
// 825 yang benar-benar ada di disk), begitu juga v1_mst_product.preview_img.
//
// 1 kartu = 1 file, dipakai bersama banyak produk -> file disalin sekali per
// mastercard_id, bukan sekali per produk (hemat ~100 MB duplikasi).
// Idempotent: file yang sudah ada tidak disalin ulang kecuali $force.
class ProductImageIngestService
{
    // Master Card lama ada di instalasi ScriptCase V1 yang diarsip apa adanya ke
    // repo; tidak ada sumber lain untuk gambar-gambar ini.
    public const SOURCE_DIR = 'docs/legacy_scriptcase/tm/_lib/file/img';

    public const TARGET_DIR = 'product/card';

    public const THUMB_MAX = 480;

    public function run(string $actor, bool $force = false): array
    {
        ini_set('memory_limit', '1G');
        set_time_limit(1800);

        $report = [
            'cards_with_filename' => 0,
            'files_copied' => 0,
            'files_reused' => 0,
            'files_missing' => 0,
            'thumbs_built' => 0,
            'products_linked' => 0,
            'products_multi_card' => 0,
            'bytes' => 0,
            'errors' => [],
        ];

        $sourceDir = base_path(self::SOURCE_DIR);
        if (! is_dir($sourceDir)) {
            $report['errors'][] = 'Folder sumber tidak ditemukan: '.$sourceDir;

            return $report;
        }

        $this->syncPreviewFilenames();

        $disk = $this->indexSourceFiles($sourceDir);
        $cards = $this->cardsWithImage();
        $report['cards_with_filename'] = count($cards);

        $fullDir = storage_path('app/public/'.self::TARGET_DIR);
        $thumbDir = $fullDir.DIRECTORY_SEPARATOR.'thumb';
        File::ensureDirectoryExists($fullDir);
        File::ensureDirectoryExists($thumbDir);

        // mastercard_id => [image_path, image_thumb_path]
        $resolved = [];

        foreach ($cards as $cardId => $filename) {
            $key = $this->normalizeName($filename);

            if (! isset($disk[$key])) {
                $report['files_missing']++;

                continue;
            }

            $ext = strtolower(pathinfo($disk[$key], PATHINFO_EXTENSION)) ?: 'jpg';
            $base = $cardId.'.'.$ext;
            $srcPath = $sourceDir.DIRECTORY_SEPARATOR.$disk[$key];
            $dstPath = $fullDir.DIRECTORY_SEPARATOR.$base;
            $thumbPath = $thumbDir.DIRECTORY_SEPARATOR.$base;

            try {
                if ($force || ! is_file($dstPath)) {
                    File::copy($srcPath, $dstPath);
                    $report['files_copied']++;
                } else {
                    $report['files_reused']++;
                }
                $report['bytes'] += filesize($dstPath);

                if (($force || ! is_file($thumbPath)) && $this->makeThumb($dstPath, $thumbPath, $ext)) {
                    $report['thumbs_built']++;
                }

                $resolved[$cardId] = [
                    self::TARGET_DIR.'/'.$base,
                    is_file($thumbPath) ? self::TARGET_DIR.'/thumb/'.$base : self::TARGET_DIR.'/'.$base,
                ];
            } catch (\Throwable $e) {
                $report['errors'][] = "card {$cardId}: {$e->getMessage()}";
            }
        }

        [$linked, $multi] = $this->linkProducts($resolved, $actor);
        $report['products_linked'] = $linked;
        $report['products_multi_card'] = $multi;

        return $report;
    }

    // prod_card_legacy hasil MasterCardMigrationService versi awal tidak membawa
    // preview_print; disinkronkan di sini supaya engine ini tidak perlu membaca
    // tm2old lagi di tahap penyalinan file.
    protected function syncPreviewFilenames(): void
    {
        DB::connection('tm2old')->table('mst_card')
            ->whereNotNull('preview_print')->where('preview_print', '<>', '')
            ->orderBy('mastercard_id')
            ->chunk(500, function ($chunk) {
                foreach ($chunk as $row) {
                    DB::connection('tm2mst')->table('prod_card_legacy')
                        ->where('legacy_mastercard_id', $row->mastercard_id)
                        ->update(['preview_filename' => trim((string) $row->preview_print)]);
                }
            });
    }

    /** @return array<string,string> nama ternormalisasi => nama file asli */
    protected function indexSourceFiles(string $dir): array
    {
        $map = [];

        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..' || ! preg_match('/\.(jpe?g|png)$/i', $file)) {
                continue;
            }
            $map[$this->normalizeName($file)] = $file;
        }

        return $map;
    }

    /** @return array<int,string> mastercard_id => nama file */
    protected function cardsWithImage(): array
    {
        return DB::connection('tm2mst')->table('prod_card_legacy')
            ->whereNotNull('preview_filename')->where('preview_filename', '<>', '')
            ->whereNotNull('legacy_mastercard_id')
            ->pluck('preview_filename', 'legacy_mastercard_id')
            ->all();
    }

    // Nama file lama beda kapitalisasi & spasi antara isi kolom dan nama di disk
    // -- dibandingkan setelah diratakan, bukan mentah-mentah.
    protected function normalizeName(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }

    protected function makeThumb(string $src, string $dst, string $ext): bool
    {
        $info = @getimagesize($src);
        if (! $info) {
            return false;
        }

        [$w, $h] = $info;
        if ($w <= 0 || $h <= 0) {
            return false;
        }

        $scale = min(1, self::THUMB_MAX / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));

        $image = match (strtolower($ext)) {
            'png' => @imagecreatefrompng($src),
            default => @imagecreatefromjpeg($src),
        };

        if (! $image) {
            return false;
        }

        $thumb = imagecreatetruecolor($tw, $th);
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $tw, $th, $w, $h);
        $ok = imagejpeg($thumb, $dst, 78);

        imagedestroy($thumb);
        imagedestroy($image);

        return (bool) $ok;
    }

    /**
     * @param  array<int,array{0:string,1:string}>  $resolved
     * @return array{0:int,1:int}
     */
    protected function linkProducts(array $resolved, string $actor): array
    {
        if (! $resolved) {
            return [0, 0];
        }

        $pairs = DB::connection('tm2mst')->table('prod_product_component_legacy as c')
            ->join('prod_card_legacy as k', 'k.id_card_legacy', '=', 'c.card_legacy_id')
            ->whereNotNull('c.product_id')
            ->whereIn('k.legacy_mastercard_id', array_keys($resolved))
            ->distinct()
            ->get(['c.product_id', 'k.legacy_mastercard_id']);

        // Produk bisa punya lebih dari 1 kartu (revisi desain) -- ambil kartu ber-id
        // tertinggi = revisi terbaru, supaya hasilnya deterministik dan bukan
        // tergantung urutan baris yang kebetulan keluar duluan.
        $pick = [];
        $multi = 0;
        foreach ($pairs as $p) {
            $pid = (int) $p->product_id;
            $cid = (int) $p->legacy_mastercard_id;
            if (isset($pick[$pid])) {
                $multi++;
                $pick[$pid] = max($pick[$pid], $cid);
            } else {
                $pick[$pid] = $cid;
            }
        }

        // Dibalik jadi cardId => daftar produk supaya update bisa dibatch per kartu.
        $byCard = [];
        foreach ($pick as $pid => $cid) {
            $byCard[$cid][] = $pid;
        }

        $linked = 0;
        foreach ($byCard as $cid => $productIds) {
            [$path, $thumb] = $resolved[$cid];
            foreach (array_chunk($productIds, 500) as $chunk) {
                DB::connection('tm2mst')->table('prod_product')
                    ->whereIn('id_product', $chunk)
                    ->update([
                        'image_path' => $path,
                        'image_thumb_path' => $thumb,
                        'image_source_card_id' => $cid,
                        'updated_by' => $actor,
                    ]);
                $linked += count($chunk);
            }
        }

        return [$linked, $multi];
    }
}
