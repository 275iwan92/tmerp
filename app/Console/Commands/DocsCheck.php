<?php

/*
 * REMARK
 * Tujuan       : Memeriksa kartu Technical Guide (docs/tg) terhadap kenyataan: berkas/baris, tabel.kolom, kelas, tautan antarkartu, key engine di registry. Baca saja.
 * Sumber hulu  : docs/tg/**.md, information_schema (semua tm2* dan ddt*), repo (app, resources, routes, tests, docs, packages, database), App\Support\Engines\EngineRegistry.
 * Input        : --path= (bawaan docs/tg), --strict (peringatan ikut menggagalkan), --list (cetak semua temuan). Daftar kecuali: docs/tg/_allow.txt (satu token per baris).
 * Output       : Ringkasan jumlah referensi, ERROR (menggagalkan) dan WARN (tidak menggagalkan kecuali --strict). Kode keluar 1 bila ada ERROR.
 * Dipakai oleh : Developer/agen sebelum commit docs/tg; hook pre-commit (Fase 4, menyusul).
 * Status       : BARU 2026-10-16, tahap 1 (pemeriksaan referensi). Belum: kartu usang, relasi sepihak, kecocokan pohon menu, hook.
 */

namespace App\Console\Commands;

use App\Support\Engines\EngineRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DocsCheck extends Command
{
    protected $signature = 'docs:check {--path=docs/tg} {--strict} {--list} {--staged : also fail when a staged file is cited by a card whose card is not staged}';

    protected $description = 'Check the Technical Guide cards against the database, the code and each other (read only)';

    private const ROOTS = ['app', 'resources', 'routes', 'tests', 'docs', 'packages', 'database'];

    private array $errors = [];

    private array $warns = [];

    /** @var array<string,list<string>> repo-relative cited file => cards that cite it */
    private array $cited = [];

    private array $stat = ['files' => 0, 'path' => 0, 'table_column' => 0, 'bare' => 0, 'class' => 0, 'link' => 0, 'engine_key' => 0];

    public function handle(): int
    {
        $root = base_path($this->option('path'));
        if (! is_dir($root)) {
            $this->error("Folder not found: $root");

            return self::FAILURE;
        }
        [$tables, $columns, $anyColumn] = $this->schema();
        $index = $this->fileIndex();
        $allow = is_file("$root/_allow.txt") ? array_filter(array_map('trim', file("$root/_allow.txt")), fn ($l) => $l !== '' && ! str_starts_with($l, '#')) : [];
        $engineKeys = array_column(EngineRegistry::all(), 'key');
        $tests = $this->testNames();

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! str_ends_with((string) $file, '.md')) {
                continue;
            }
            $this->stat['files']++;
            $rel = $this->rel((string) $file);
            $text = (string) file_get_contents((string) $file);

            foreach ($this->tokens($text) as $tok) {
                $this->checkToken($tok, $rel, dirname((string) $file), $index, $tables, $columns, $anyColumn, $allow, $tests, $engineKeys);
            }
            foreach ($this->links($text) as $link) {
                $this->stat['link']++;
                if (! file_exists(dirname((string) $file).'/'.$link)) {
                    $this->errors[] = "$rel: broken link $link";
                }
            }
            if (str_contains($rel, '_engine_') && preg_match('/\*\*Registry\*\*: key `([^`]+)`/', $text, $m)) {
                $this->stat['engine_key']++;
                if (! in_array($m[1], $engineKeys, true)) {
                    $this->errors[] = "$rel: registry key {$m[1]} not in EngineRegistry";
                }
            }
        }

        $this->checkStale();

        if ($this->option('staged')) {
            $this->checkStaged();
        }

        return $this->report();
    }

    private function checkToken(string $tok, string $rel, string $dir, array $index, array $tables, array $columns, array $anyColumn, array $allow, array $tests, array $engineKeys): void
    {
        if (in_array($tok, $allow, true)) {
            return;
        }
        if (preg_match('~^([\w./\\\\-]+\.(?:php|js|md|json|svg|yaml))(?::(\d+)(?:-(\d+))?)?$~', $tok, $p)) {
            $this->stat['path']++;
            $path = str_replace('\\', '/', $p[1]);
            $full = file_exists(base_path($path)) ? base_path($path) : (file_exists("$dir/$path") ? "$dir/$path" : null);
            if (! $full) {
                $hits = $index[basename($path)] ?? [];
                if (count($hits) === 1) {
                    $full = base_path($hits[0]);
                } elseif (count($hits) > 1) {
                    $this->warns[] = "$rel: ambiguous path $tok (".count($hits).' files)';
                } else {
                    $this->errors[] = "$rel: missing file $tok";
                }
            }
            if ($full) {
                $this->cited[$this->rel($full)][] = $rel;
            }
            if ($full && ! empty($p[2]) && (int) ($p[3] ?? $p[2]) > count(file($full))) {
                $this->warns[] = "$rel: line beyond end of file $tok";
            }

            return;
        }
        if (preg_match('/^(?:(?:tm2|ddt)[a-z]+\.)?([a-z][a-z0-9]*(?:_[a-z0-9]+)+)\.([a-z_][a-z0-9_]*)$/', $tok, $p) && isset($tables[$p[1]])) {
            $this->stat['table_column']++;
            if (! isset($columns[$p[1]][$p[2]])) {
                $this->errors[] = "$rel: no column {$p[2]} in table {$p[1]}";
            }

            return;
        }
        if (preg_match('/^(?:App|Tests)\\\\[\w\\\\]+(?:::\w+)?$/', $tok)) {
            $this->stat['class']++;
            $base = base_path(str_replace(['App\\', 'Tests\\', '\\'], ['app/', 'tests/', '/'], explode('::', $tok)[0]));
            if (! file_exists("$base.php") && ! is_dir($base)) {
                $this->errors[] = "$rel: missing class $tok";
            }

            return;
        }
        if (preg_match('/^test_[a-z0-9_]+$/', $tok)) {
            $this->stat['bare']++;
            if (! isset($tests[$tok])) {
                $this->warns[] = "$rel: test method not found $tok";
            }

            return;
        }
        if (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)+$/', $tok)) {
            $this->stat['bare']++;
            if (! isset($tables[$tok]) && ! isset($anyColumn[$tok]) && ! in_array($tok, $engineKeys, true)) {
                $this->warns[] = "$rel: unknown name $tok (no table or column with this name)";
            }
        }
    }

    // Stale card: a cited file was committed after the card's last commit (one git call over the last 120 days of history; only a WARN).
    private function checkStale(): void
    {
        $files = array_values(array_unique([...array_keys($this->cited), ...array_merge(...array_values($this->cited) ?: [[]])]));
        if (! $files) {
            return;
        }
        $last = [];
        $out = (string) shell_exec('git -C '.escapeshellarg(base_path()).' log --format=@%cs --name-only --since=120.days 2>&1');
        $date = null;
        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            if (str_starts_with($line, '@')) {
                $date = substr($line, 1);
            } elseif ($line !== '' && $date && ! isset($last[$line])) {
                $last[$line] = $date;
            }
        }
        foreach ($this->cited as $code => $cards) {
            foreach (array_unique($cards) as $card) {
                if (isset($last[$code], $last[$card]) && $last[$code] > $last[$card]) {
                    $this->warns[] = "$card: stale? $code changed {$last[$code]}, card last changed {$last[$card]}";
                }
            }
        }
    }

    // Hook rule (Jo 2026-10-16): code that a card cites changes => the card must be touched in the same commit.
    private function checkStaged(): void
    {
        $staged = array_filter(explode("\n", (string) shell_exec('git -C '.escapeshellarg(base_path()).' diff --cached --name-only')));
        foreach ($staged as $file) {
            foreach (array_unique($this->cited[$file] ?? []) as $card) {
                if (! in_array($card, $staged, true)) {
                    $this->errors[] = "$file is cited by $card, which is not staged: check the card against the change, update it (and its Verified date), then stage it";
                }
            }
        }
    }

    /** @return list<string> backtick tokens outside fenced code */
    private function tokens(string $text): array
    {
        $text = preg_replace('/```.*?```/s', '', $text) ?? $text;
        preg_match_all('/`([^`\n]{2,120})`/', $text, $m);

        return array_values(array_unique(array_map('trim', $m[1])));
    }

    /** @return list<string> relative markdown link targets without anchors */
    private function links(string $text): array
    {
        preg_match_all('/\]\((\.{1,2}\/[^)#\s]+)/', $text, $m);

        return array_values(array_unique($m[1]));
    }

    /** @return array{0: array<string,int>, 1: array<string,array<string,int>>, 2: array<string,int>} */
    private function schema(): array
    {
        $tables = $columns = $any = [];
        foreach (DB::connection('tm2mst')->select("select table_name t, column_name c from information_schema.columns where table_schema like 'ddt%' or table_schema like 'tm2%'") as $r) {
            $tables[$r->t] = 1;
            $columns[$r->t][$r->c] = 1;
            $any[$r->c] = 1;
        }

        return [$tables, $columns, $any];
    }

    /** @return array<string,list<string>> basename => repo-relative paths */
    private function fileIndex(): array
    {
        $idx = [];
        foreach (self::ROOTS as $d) {
            if (! is_dir(base_path($d))) {
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($d), \FilesystemIterator::SKIP_DOTS)) as $f) {
                $p = $this->rel((string) $f);
                if (! str_contains($p, 'vendor/') && ! str_contains($p, 'node_modules/')) {
                    $idx[basename($p)][] = $p;
                }
            }
        }

        return $idx;
    }

    /** @return array<string,int> test method names found under tests/ */
    private function testNames(): array
    {
        $names = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('tests'), \FilesystemIterator::SKIP_DOTS)) as $f) {
            if (str_ends_with((string) $f, '.php') && preg_match_all('/function (test_[a-z0-9_]+)/i', (string) file_get_contents((string) $f), $m)) {
                foreach ($m[1] as $n) {
                    $names[$n] = 1;
                }
            }
        }

        return $names;
    }

    private function rel(string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen(base_path()))), '/');
    }

    // Hook mode: a commit is only blocked by its own cards; errors in cards that are not part of this commit (other sessions' unfinished work) become WARN.
    private function limitToStaged(): void
    {
        $staged = array_filter(explode("\n", (string) shell_exec('git -C '.escapeshellarg(base_path()).' diff --cached --name-only')));
        $own = [];
        foreach ($this->errors as $e) {
            $subject = strstr($e, ':', true);
            if (str_starts_with($e, 'docs/tg/') && ! in_array($subject, $staged, true)) {
                $this->warns[] = 'not in this commit: '.$e;
            } else {
                $own[] = $e;
            }
        }
        $this->errors = $own;
    }

    private function report(): int
    {
        if ($this->option('staged')) {
            $this->limitToStaged();
        }

        $this->line(sprintf('docs:check: %d files; refs: %d paths, %d table.column, %d names, %d classes, %d links, %d engine keys',
            $this->stat['files'], $this->stat['path'], $this->stat['table_column'], $this->stat['bare'], $this->stat['class'], $this->stat['link'], $this->stat['engine_key']));
        $this->line(sprintf('ERROR %d, WARN %d', count($this->errors), count($this->warns)));
        foreach ($this->errors as $e) {
            $this->error($e);
        }
        if ($this->option('list')) {
            foreach ($this->warns as $w) {
                $this->warn($w);
            }
        }
        $registered = array_column(EngineRegistry::all(), 'key');
        $this->line('Engines in registry: '.count($registered).' (cards exist for the ones named in *_engine_* files; see docs/tg/01_engine-map.md)');

        return $this->errors || ($this->option('strict') && $this->warns) ? self::FAILURE : self::SUCCESS;
    }
}
