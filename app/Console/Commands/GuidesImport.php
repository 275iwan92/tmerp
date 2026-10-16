<?php

/*
 * REMARK
 * Tujuan       : Memuat panduan docs/guides/<modul>/NN_slug.md ke tabel sys_guide; bagian sesudah baris penanda johiwan-only disimpan terpisah sebagai bagian rumus.
 * Sumber hulu  : Berkas markdown di docs/guides (sumber tulisan, ikut git).
 * Input        : --path= (bawaan docs/guides); nama folder = modul, awalan NN_ = urutan, sisanya = slug.
 * Output       : Baris sys_guide (kunci slug, diperbarui bila berubah); judul dan ringkasan dari MdDigest.
 * Dipakai oleh : Claude / developer setelah menulis atau merevisi panduan; layar Setup > Help and Support > Guides.
 * Status       : Aktif.
 */

namespace App\Console\Commands;

use App\Models\Sys\SysGuide;
use App\Services\Sys\MdDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GuidesImport extends Command
{
    protected $signature = 'guides:import {--path=docs/guides}';

    protected $description = 'Load the guides (docs/guides/<module>/NN_slug.md) into the Guides screen';

    private const SECRET = '<!-- johiwan-only -->';

    public function handle(): int
    {
        $root = base_path($this->option('path'));
        if (! is_dir($root)) {
            $this->error("Folder not found: $root");

            return self::FAILURE;
        }
        $n = 0;
        foreach (glob($root.'/*/*.md') ?: [] as $file) {
            preg_match('/^(\d+)_(.+)\.md$/', basename($file), $m);
            $module = basename(dirname($file));
            $text = str_replace("\r\n", "\n", (string) file_get_contents($file));
            [$body, $secret] = array_pad(explode(self::SECRET, $text, 2), 2, null);
            $digest = MdDigest::fromText($body); // ringkasan hanya dari bagian umum: bagian rumus tidak boleh bocor ke kartu
            SysGuide::query()->updateOrCreate(['slug' => $module.'-'.($m[2] ?? basename($file, '.md'))], [
                'module' => Str::headline($module), 'title' => Str::limit((string) $digest['title'], 200, ''),
                'summary' => Str::limit($digest['summary'], 600, ''), 'body' => trim($body), 'body_secret' => $secret === null ? null : trim($secret),
                'sort_order' => (int) ($m[1] ?? 0), 'source_path' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $file),
                'line_count' => substr_count($text, "\n") + 1,
            ]);
            $n++;
        }
        $this->info("Guides: $n loaded.");

        return self::SUCCESS;
    }
}
