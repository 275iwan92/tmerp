<?php

/*
 * REMARK
 * Tujuan       : Perpustakaan dokumen usang: memasukkan folder arsip (.md) ke database, mencari topiknya, dan membaca isinya, tanpa AI.
 * Sumber hulu  : Folder arsip (bawaan C:\Users\iwanp\tmerp_unused) dan MANIFEST.md di dalamnya (format: tanggal | path | alasan | commit).
 * Input        : action add|search|show; target = folder (add), kata kunci (search), atau id (show); --limit.
 * Output       : Baris tm2sys.sys_md_library (kunci original_path, diperbarui bila isi berubah); daftar hasil atau isi dokumen di layar konsol.
 * Dipakai oleh : Claude / developer saat butuh melihat dokumen lama (php artisan md:library search "kata kunci" --env=mockup).
 * Status       : Aktif (ddt*).
 */

namespace App\Console\Commands;

use App\Models\Sys\SysMdLibrary;
use App\Services\Sys\MdDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MdLibrary extends Command
{
    protected $signature = 'md:library {action : add, search or show} {target? : folder (add), keywords (search) or id (show)} {--limit=10}';

    protected $description = 'Obsolete-document library: store archived .md files in the database and search them by topic';

    private const DEFAULT_FOLDER = 'C:\\Users\\iwanp\\tmerp_unused';

    public function handle(): int
    {
        $target = (string) $this->argument('target');

        return match ($this->argument('action')) {
            'add' => $this->add($target !== '' ? $target : self::DEFAULT_FOLDER),
            'search' => $this->search($target),
            'show' => $this->show((int) $target),
            default => $this->fail('Action must be add, search or show.'),
        };
    }

    private function add(string $root): int
    {
        if (! is_dir($root)) {
            $this->error("Folder not found: $root");

            return self::FAILURE;
        }
        $manifest = $this->manifest($root);
        $n = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($root, '\\/')) + 1));
            if ($file->getExtension() !== 'md' || str_contains($rel, 'node_modules/') || $rel === 'MANIFEST.md') {
                continue;
            }
            $body = (string) file_get_contents($file->getPathname());
            $digest = MdDigest::of($file->getPathname());
            [$when, $reason] = $manifest[$rel] ?? [date('Y-m-d', $file->getMTime()), null];
            SysMdLibrary::query()->updateOrCreate(['original_path' => $rel], [
                'file_name' => $file->getFilename(),
                'title' => Str::limit((string) $digest['title'], 200, ''),
                'summary' => Str::limit($digest['summary'], 600, ''),
                'line_count' => substr_count($body, "\n") + 1,
                'body' => $body,
                'reason' => $reason ? Str::limit($reason, 500, '') : null,
                'archived_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $when) ? $when : null,
            ]);
            $n++;
        }
        $this->info("Library: $n documents stored.");

        return self::SUCCESS;
    }

    /** @return array<string, array{0: string, 1: string}> path => [date, reason] */
    private function manifest(string $root): array
    {
        $map = [];
        $file = rtrim($root, '\\/').DIRECTORY_SEPARATOR.'MANIFEST.md';
        foreach (is_file($file) ? (preg_split('/\R/', (string) file_get_contents($file)) ?: []) : [] as $line) {
            $c = array_map('trim', explode(' | ', $line));
            if (count($c) >= 3 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $c[0])) {
                $map[str_replace('\\', '/', $c[1])] = [$c[0], $c[2]];
            }
        }

        return $map;
    }

    private function search(string $q): int
    {
        if ($q === '') {
            $this->error('Give keywords to search for.');

            return self::FAILURE;
        }
        $rows = SysMdLibrary::query()->select(['id_md_library', 'original_path', 'title', 'reason', 'archived_at', 'line_count'])
            ->selectRaw('match(title, summary, body) against (? in natural language mode) as score', [$q])
            ->whereRaw('match(title, summary, body) against (? in natural language mode)', [$q])
            ->orderByDesc('score')->limit((int) $this->option('limit'))->get();
        if ($rows->isEmpty()) { // kata pendek / umum tidak masuk indeks FULLTEXT
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $rows = SysMdLibrary::query()->select(['id_md_library', 'original_path', 'title', 'reason', 'archived_at', 'line_count'])
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like))
                ->limit((int) $this->option('limit'))->get();
        }
        foreach ($rows as $r) {
            $this->line(sprintf('#%d  %s  [%dL, %s]', $r->id_md_library, $r->title ?: $r->original_path, $r->line_count, $r->archived_at ?? '-'));
            $this->line("     {$r->original_path}".($r->reason ? "  :: {$r->reason}" : ''));
        }
        $this->info($rows->count().' found. Read one with: php artisan md:library show <id>');

        return self::SUCCESS;
    }

    private function show(int $id): int
    {
        $doc = SysMdLibrary::query()->find($id);
        if (! $doc) {
            $this->error("No document #$id.");

            return self::FAILURE;
        }
        $this->line("# {$doc->original_path}".($doc->reason ? "  ({$doc->reason})" : ''));
        $this->line($doc->body);

        return self::SUCCESS;
    }
}
