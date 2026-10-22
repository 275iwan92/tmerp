<?php

/*
 * REMARK
 * Tujuan       : Menjawab "apakah konsep ini sudah ada?" sebelum agen mengusulkan tabel, kolom, layar, atau aturan baru; hasilnya berupa blok "Already exists? Searched where, result" yang bisa ditempel.
 * Sumber hulu  : docs/tg (kartu), docs/tg/_synonyms.txt, sys_app_menu, information_schema (semua tm2* dan ddt*), nama berkas app/resources/routes/packages, sys_help_topic, sys_guide.
 * Input        : argumen kata atau frasa (Indonesia atau Inggris), --limit= per sumber (bawaan 8).
 * Output       : Per sumber: jumlah dan contoh temuan; baris akhir menyatakan sumber mana yang kosong. Baca saja.
 * Dipakai oleh : Agen/developer sebelum mengusulkan sesuatu yang baru (protokol TG, Fase 5).
 * Status       : BARU 2026-10-16, tahap 1. 2026-10-22: temuan tabel/kolom tanpa duplikat tm2*/ddt* dan kecocokan persis dulu (urutan tetap). Belum: md:library, pencocokan kata dasar (stemming).
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ConceptFind extends Command
{
    protected $signature = 'concept:find {term : word or phrase, Indonesian or English} {--limit=8}';

    protected $description = 'Find whether a concept already exists (TG cards, menu, DB, screens, code, help) before proposing something new';

    public function handle(): int
    {
        $term = mb_strtolower(trim((string) $this->argument('term')));
        $variants = $this->variants($term);
        $snake = array_values(array_unique(array_map(fn ($v) => str_replace([' ', '-'], '_', $v), $variants)));
        $limit = (int) $this->option('limit');
        $this->line('Variants searched: '.implode(' | ', $variants));
        $found = [];

        $found['TG cards (docs/tg)'] = $this->cards($variants);
        $found['Menu (sys_app_menu)'] = $this->menu($variants);
        $found['Tables and columns (all tm2*/ddt*)'] = $this->schema($snake);
        $found['Files (app, resources, routes, packages)'] = $this->files($snake, $variants);
        $found['Help topics and guides (sys_help_topic, sys_guide)'] = $this->help($variants);

        $this->newLine();
        $this->line('Already exists? Searched where, result');
        $empty = [];
        foreach ($found as $source => $hits) {
            if (! $hits) {
                $empty[] = $source;

                continue;
            }
            $this->line("- $source: ".count($hits).' hit(s)');
            foreach (array_slice($hits, 0, $limit) as $h) {
                $this->line("    $h");
            }
        }
        if ($empty) {
            $this->line('- Nothing found in: '.implode('; ', $empty));
        }

        return self::SUCCESS;
    }

    /** @return list<string> the term plus its synonyms from docs/tg/_synonyms.txt (lines: a = b = c) */
    private function variants(string $term): array
    {
        $out = [$term];
        $file = base_path('docs/tg/_synonyms.txt');
        foreach (is_file($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }
            $group = array_map(fn ($s) => mb_strtolower(trim($s)), explode('=', $line));
            if (in_array($term, $group, true)) {
                $out = [...$out, ...$group];
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    private function cards(array $variants): array
    {
        $hits = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('docs/tg'), \FilesystemIterator::SKIP_DOTS)) as $f) {
            if (! str_ends_with((string) $f, '.md')) {
                continue;
            }
            $lines = file((string) $f, FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($lines as $i => $line) {
                foreach ($variants as $v) {
                    if (mb_stripos($line, $v) !== false) {
                        $hits[] = $this->rel((string) $f).':'.($i + 1).'  '.mb_substr(trim($line), 0, 110);
                        break 2;
                    }
                }
            }
        }

        return $hits;
    }

    private function menu(array $variants): array
    {
        $q = DB::connection('tm2sys')->table('sys_app_menu')->where('activate', 1)->where(function ($w) use ($variants) {
            foreach ($variants as $v) {
                $w->orWhere('label', 'like', "%$v%")->orWhere('target', 'like', '%'.str_replace(' ', '-', $v).'%');
            }
        });

        return $q->limit(40)->get(['label', 'target'])->map(fn ($r) => "{$r->label} ({$r->target})")->all();
    }

    private function schema(array $snake): array
    {
        $hits = [];
        foreach ($snake as $s) {
            // DISTINCT (tm2* dan ddt* kembar) + kecocokan persis dulu, supaya 8 baris teratas tidak bergantung urutan information_schema.
            $rows = DB::connection('tm2mst')->select("select distinct table_name t, column_name c from information_schema.columns where (table_schema like 'ddt%' or table_schema like 'tm2%') and (table_name like ? or column_name like ?) order by (column_name = ? or table_name = ?) desc, table_name, column_name limit 200", ["%$s%", "%$s%", $s, $s]);
            foreach ($rows as $r) {
                $hits[$r->t.'.'.$r->c] = "{$r->t}.{$r->c}";
            }
        }

        return array_values($hits);
    }

    private function files(array $snake, array $variants): array
    {
        $needles = array_unique([...$snake, ...array_map(fn ($v) => str_replace([' ', '_'], '-', $v), $variants), ...array_map(fn ($v) => str_replace([' ', '-', '_'], '', $v), $variants)]);
        $hits = [];
        foreach (['app', 'resources/views', 'routes', 'packages'] as $d) {
            if (! is_dir(base_path($d))) {
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($d), \FilesystemIterator::SKIP_DOTS)) as $f) {
                $name = mb_strtolower(basename((string) $f));
                foreach ($needles as $n) {
                    if ($n !== '' && str_contains($name, $n) && ! str_contains((string) $f, 'vendor')) {
                        $hits[] = $this->rel((string) $f);
                        break;
                    }
                }
            }
        }

        return $hits;
    }

    private function help(array $variants): array
    {
        $sys = DB::connection('tm2sys');
        $hits = [];
        foreach ($variants as $v) {
            foreach ($sys->table('sys_help_topic')->where('activate', 1)->where(fn ($w) => $w->where('title', 'like', "%$v%")->orWhere('keywords', 'like', "%$v%"))->limit(20)->get(['topic_key', 'title']) as $r) {
                $hits["topic:{$r->topic_key}"] = "help topic {$r->topic_key}: {$r->title}";
            }
            foreach ($sys->table('sys_guide')->where(fn ($w) => $w->where('title', 'like', "%$v%")->orWhere('summary', 'like', "%$v%"))->limit(20)->get(['slug', 'title']) as $r) {
                $hits["guide:{$r->slug}"] = "guide {$r->slug}: {$r->title}";
            }
        }

        return array_values($hits);
    }

    private function rel(string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen(base_path()))), '/');
    }
}
