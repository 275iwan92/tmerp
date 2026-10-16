<?php

/*
 * REMARK
 * Tujuan       : Memuat panduan docs/guides/<modul>/NN_slug.md (section guide) dan catatan perancangan docs/design-notes/<area>/NN_slug.md (section design) dan Technical Guide docs/tg/<modul>/<sub-modul>/NN_slug.md (section tg) ke tabel sys_guide; bagian sesudah baris penanda johiwan-only disimpan terpisah sebagai bagian rumus.
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
    protected $signature = 'guides:import {--path= : only this folder (as guides), default docs/guides and docs/design-notes}';

    protected $description = 'Load the guides (docs/guides/<module>/NN_slug.md) into the Guides screen';

    private const SECRET = '<!-- johiwan-only -->';

    public function handle(): int
    {
        $n = 0;
        foreach ($this->option('path') ? [[$this->option('path'), 'guide']] : [['docs/guides', 'guide'], ['docs/design-notes', 'design']] as [$path, $section]) {
            $root = base_path($path);
            if (! is_dir($root)) {
                $this->error("Folder not found: $root");

                return self::FAILURE;
            }
            foreach (glob($root.'/*/*.md') ?: [] as $file) {
                preg_match('/^(\d+)_(.+)\.md$/', basename($file), $m);
                $module = basename(dirname($file));
                $text = str_replace('
', '
', (string) file_get_contents($file));
                [$body, $secret] = array_pad(explode(self::SECRET, $text, 2), 2, null);
                $digest = MdDigest::fromText($body); // ringkasan hanya dari bagian umum: bagian rumus tidak boleh bocor ke kartu
                SysGuide::query()->updateOrCreate(['slug' => ($section === 'design' ? 'design-' : '').$module.'-'.($m[2] ?? basename($file, '.md'))], [
                    'section' => $section, 'module' => Str::headline($module), 'title' => Str::limit((string) $digest['title'], 200, ''),
                    'summary' => Str::limit($digest['summary'], 600, ''), 'body' => trim($body), 'body_secret' => $secret === null ? null : trim($secret),
                    'sort_order' => (int) ($m[1] ?? 0), 'source_path' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $file),
                    'line_count' => substr_count($text, '
') + 1,
                ]);
                $n++;
            }
        }
        if (! $this->option('path')) {
            $n += $this->importTg();
        }
        $this->info("Guides: $n loaded.");

        return self::SUCCESS;
    }

    // Technical Guide (English): docs/tg/<module>/<sub-module>/NN_slug.md plus docs/tg/NN_slug.md (overview). Slug = path without extension, module chip = "Module · Sub-module".
    private function importTg(): int
    {
        $root = base_path('docs/tg');
        $n = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! str_ends_with((string) $file, '.md')) {
                continue;
            }
            $parts = explode('/', str_replace('\\', '/', substr((string) $file, strlen($root) + 1)));
            $name = array_pop($parts);
            preg_match('/^(\d+)_(.+)\.md$/', $name, $m);
            $text = str_replace("\r\n", "\n", (string) file_get_contents((string) $file));
            [$body, $secret] = array_pad(explode(self::SECRET, $text, 2), 2, null);
            $digest = MdDigest::fromText($body);
            SysGuide::query()->updateOrCreate(['slug' => 'tg-'.Str::slug(implode('-', [...$parts, $m[1] ?? '', $m[2] ?? basename($name, '.md')]))], [
                'section' => 'tg', 'module' => $parts ? Str::headline(implode(' · ', array_slice($parts, 0, 2))) : 'Overview', 'title' => Str::limit((string) $digest['title'], 200, ''),
                'summary' => Str::limit($digest['summary'], 600, ''), 'body' => trim($body), 'body_secret' => $secret === null ? null : trim($secret),
                'sort_order' => (int) ($m[1] ?? 0), 'source_path' => 'docs/tg/'.implode('/', [...$parts, $name]), 'line_count' => substr_count($text, "\n") + 1,
            ]);
            $n++;
        }

        return $n;
    }
}
