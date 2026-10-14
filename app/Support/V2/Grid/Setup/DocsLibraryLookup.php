<?php

/*
 * REMARK
 * Tujuan       : Layar Setup > Others > Old Docs Library: dokumen .md usang dibaca seperti artikel blog (indeks bergaya WordPress, satu artikel penuh per halaman) dan dicari per topik.
 * Sumber hulu  : tm2sys.sys_md_library (diisi php artisan md:library add dari folder arsip tmerp_unused).
 * Input        : ?q= (cari di judul, ringkasan, dan isi lewat FULLTEXT), ?doc= (id artikel yang dibaca); chip jenis dokumen di indeks.
 * Output       : feed(): kartu indeks (judul, meta, ringkasan, tautan Read article); article(): satu artikel penuh (markdown). Hanya baca, tidak ada yang tersimpan.
 * Dipakai oleh : Menu SET_DOCS_LIBRARY (v2:setup-docs-library), resources/views/v2/screens/setup-docs-library.blade.php.
 * Status       : Aktif (ddt*).
 */

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysMdLibrary;
use App\Support\V2\Grid\DbLookup;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class DocsLibraryLookup extends DbLookup
{
    private const LIST_COLUMNS = ['id_md_library', 'file_name', 'original_path', 'title', 'summary', 'line_count', 'reason', 'archived_at'];

    private const LIMIT = 200;

    public function slug(): string
    {
        return 'setup-docs-library';
    }

    public function title(): string
    {
        return 'Old Docs Library';
    }

    public function module(): string
    {
        return 'SETUP';
    }

    public function moduleLabel(): string
    {
        return 'Setup';
    }

    public function group(): string
    {
        return 'Others';
    }

    public function icon(): string
    {
        return 'fa-solid fa-box-archive';
    }

    public function guide(): ?string
    {
        return 'Archived documents, read like articles. Search inside the text, then open one to read it in full. Nothing can be changed here.';
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.setup-docs-library'] + parent::screen();
    }

    protected function query(): Builder
    {
        return SysMdLibrary::query()->select(self::LIST_COLUMNS)->orderByDesc('archived_at')->orderBy('title');
    }

    protected function row(Model $m): array
    {
        return ['original_path' => $m->original_path, 'line_count' => (int) $m->line_count];
    }

    public function columns(): array
    {
        return [
            ['key' => 'original_path', 'label' => 'File', 'readonly' => true],
            ['key' => 'line_count', 'label' => 'Lines', 'type' => 'number', 'readonly' => true],
        ];
    }

    // Perpustakaan hanya dibaca; isinya datang dari php artisan md:library add.
    public function save(array $changed): array
    {
        throw ValidationException::withMessages(['row' => t('This library is read-only.')]);
    }

    // Jenis dokumen dari lokasinya (tanpa kolom baru): dipakai chip saringan indeks.
    private function kind(string $path): string
    {
        return match (true) {
            str_contains($path, 'flows_v1') || str_contains($path, '/flows/') => 'Flows v1',
            str_contains($path, 'usang_untuk_dipindah') => 'Obsolete',
            str_contains($path, 'tier4') => 'Tier 4 v1',
            str_contains($path, 'standard') => 'UI standards v1',
            str_contains($path, 'ppic_archive') => 'PPIC archive',
            str_starts_with($path, 'CLAUDE_before') => 'Rules',
            default => 'Other',
        };
    }

    private function meta(SysMdLibrary $m): string
    {
        return $m->original_path.' · '.tf($m->line_count === 1 ? ':lines line' : ':lines lines', ['lines' => $m->line_count])
            .($m->archived_at ? ' · '.tf('archived :date', ['date' => Locale::date($m->archived_at)]) : '');
    }

    // Indeks artikel: tanpa q = semua dokumen terbaru dulu; dengan q = yang memuat kata itu (FULLTEXT, lalu LIKE untuk kata pendek), dengan cuplikan.
    public function feed(string $q = ''): array
    {
        $rows = $q !== '' ? SysMdLibrary::query()->orderByDesc('archived_at')->orderBy('title') : $this->query(); // cari: isi ikut dimuat untuk cuplikan
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $hits = (clone $rows)->whereRaw('match(title, summary, body) against (? in natural language mode)', [$q])->limit(self::LIMIT)->get();
            $rows = $hits->isNotEmpty() ? $hits : (clone $rows)->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like))->limit(self::LIMIT)->get();
        } else {
            $rows = $rows->limit(self::LIMIT)->get();
        }

        $posts = $rows->map(fn (SysMdLibrary $m) => [
            'id' => $m->getKey(), 'title' => $m->title ?: $m->file_name, 'meta' => $this->meta($m),
            'summary' => $q !== '' ? $this->snippet((string) $m->body, $q, (string) $m->summary) : (string) $m->summary,
            'url' => $this->url().'?doc='.$m->getKey(), 'data' => ['kind' => $this->kind($m->original_path)],
        ])->all();

        $kinds = array_values(array_unique(array_map(fn ($p) => $p['data']['kind'], $posts)));
        $filters = [['label' => 'All']];
        foreach ($kinds as $k) {
            $filters[] = ['label' => $k, 'where' => ['kind' => $k]];
        }

        return ['posts' => $posts, 'filters' => $filters, 'source' => $this->sourceText()];
    }

    // Satu artikel penuh: judul H1 dan front matter dibuang dari isi karena sudah menjadi judul kartu.
    public function article(int $id): ?array
    {
        $m = SysMdLibrary::query()->find($id);
        if (! $m) {
            return null;
        }
        $body = preg_replace(['/\A---\R.*?\R---\R/s', '/\A\s*#\s[^\n]*\R/'], '', str_replace("\r\n", "\n", $m->body), 1) ?? $m->body;

        return ['id' => $m->getKey(), 'title' => $m->title ?: $m->file_name, 'meta' => $this->meta($m), 'body' => $body, 'note' => $m->reason,
            'data' => ['kind' => $this->kind($m->original_path)]];
    }

    // Cuplikan sekitar kata yang dicari, dari isi tanpa front matter dan tanda markdown; tanpa kecocokan persis (FULLTEXT memakai akar kata) kembali ke ringkasan.
    private function snippet(string $body, string $q, string $fallback): string
    {
        $text = preg_replace(['/\A---\R.*?\R---\R/s', '/[#*`|>_]+/', '/\s+/'], ['', ' ', ' '], $body) ?? $body;
        $pos = mb_stripos($text, $q);
        if ($pos === false) {
            return $fallback;
        }
        $from = max(0, $pos - 140);

        return ($from > 0 ? '… ' : '').trim(mb_substr($text, $from, 320)).' …';
    }
}
