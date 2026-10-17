<?php

/*
 * REMARK
 * Tujuan       : Membangun (atau memeriksa) dua diagram tiap sub-modul Technical Guide: matriks Read/Write dari tabel "Read/Write map" dan diagram relasi tabel dari tabel "Table relations".
 * Sumber hulu  : docs/tg/<modul>/<sub-modul>/00_submodule_*.md lewat App\Support\Docs\RwMap dan ErdMap.
 * Input        : --check (jangan menulis; gagal bila SVG usang atau hilang).
 * Output       : docs/tg/<modul>/img/rw-<sub-modul>.svg dan rel-<sub-modul>.svg; daftar sub-modul tanpa bagian Read/Write map.
 * Dipakai oleh : Penulis kartu TG setelah mengubah tabel Read/Write map; dijalankan sebagai php artisan docs:rw.
 * Status       : BARU 2026-10-17.
 */

namespace App\Console\Commands;

use App\Support\Docs\ErdMap;
use App\Support\Docs\RwMap;
use Illuminate\Console\Command;

class DocsRw extends Command
{
    protected $signature = 'docs:rw {--check : only compare, do not write}';

    protected $description = 'Build the Read/Write matrix SVG of every Technical Guide sub-module from its Read/Write map table';

    public function handle(): int
    {
        $stale = $missing = $built = 0;
        foreach (glob(base_path('docs/tg/*/*/00_submodule_*.md')) ?: [] as $md) {
            $rel = ltrim(str_replace('\\', '/', substr($md, strlen(base_path()))), '/');
            $text = (string) file_get_contents($md);
            foreach ([[RwMap::class, RwMap::parse($text)], [ErdMap::class, ErdMap::parse($text)]] as [$map, $rows]) {
                if ($rows === null) {
                    $missing++;

                    continue;
                }
                $svg = $map::svg($rows, $rel);
                $out = $map::svgPath($md);
                if (is_file($out) && file_get_contents($out) === $svg) {
                    continue;
                }
                if ($this->option('check')) {
                    $stale++;
                    $this->warn('stale or missing diagram '.basename($out).' for '.$rel);

                    continue;
                }
                @mkdir(dirname($out), 0777, true);
                file_put_contents($out, $svg);
                $built++;
                $this->info("written: $out");
            }
        }
        $this->line("built $built, stale $stale, missing sections $missing");

        return $stale ? self::FAILURE : self::SUCCESS;
    }
}
