<?php

/*
 * REMARK
 * Tujuan       : Layar Setup > Help and Support > Guides: panduan per modul dibaca seperti artikel blog (indeks + satu artikel penuh), dicari di dalam isinya; bagian rumus hanya tampil untuk yang lolos FormulaAccess.
 * Sumber hulu  : tm2sys.sys_guide (diisi php artisan guides:import dari docs/guides); izin rumus dari App\Support\Costing\FormulaAccess (konstanta PROCOST_FORMULA_LOGINS).
 * Input        : ?q= (cari di judul, ringkasan, dan isi umum), ?doc= (id panduan yang dibaca); chip modul di indeks.
 * Output       : feed(): kartu indeks; article(): satu panduan penuh (markdown). Hanya baca; pencarian tidak menyentuh bagian rumus.
 * Dipakai oleh : Menu SET_GUIDES (v2:setup-guides); tampilan memakai resources/views/v2/screens/setup-docs-library.blade.php yang sama dengan Old Docs Library.
 * Status       : Aktif.
 */

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysGuide;
use App\Support\Costing\FormulaAccess;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class GuidesLookup extends DbLookup
{
    private const LIMIT = 200;

    public function slug(): string
    {
        return 'setup-guides';
    }

    public function title(): string
    {
        return 'Guides';
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
        return 'Help and Support';
    }

    public function icon(): string
    {
        return 'fa-solid fa-book-open-reader';
    }

    public function guide(): ?string
    {
        return 'Guides, read like articles: how each screen and engine works. Search inside the text, then open one to read it in full. Nothing can be changed here.';
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.setup-docs-library'] + parent::screen();
    }

    protected function query(): Builder
    {
        return SysGuide::query()->orderBy('module')->orderBy('sort_order');
    }

    protected function row(Model $m): array
    {
        return ['module' => $m->module, 'title' => $m->title];
    }

    public function columns(): array
    {
        return [
            ['key' => 'module', 'label' => 'Module', 'readonly' => true],
            ['key' => 'title', 'label' => 'Title', 'readonly' => true],
        ];
    }

    // Panduan hanya dibaca; isinya datang dari php artisan guides:import.
    public function save(array $changed): array
    {
        throw ValidationException::withMessages(['row' => t('This library is read-only.')]);
    }

    // Indeks: semua panduan per modul, atau yang memuat kata q (FULLTEXT, lalu LIKE untuk kata pendek) dengan cuplikan.
    public function feed(string $q = ''): array
    {
        $rows = $this->query();
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $hits = (clone $rows)->whereRaw('match(title, summary, body) against (? in natural language mode)', [$q])->limit(self::LIMIT)->get();
            $rows = $hits->isNotEmpty() ? $hits : (clone $rows)->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like))->limit(self::LIMIT)->get();
        } else {
            $rows = $rows->limit(self::LIMIT)->get();
        }

        $posts = $rows->map(fn (SysGuide $g) => [
            'id' => $g->getKey(), 'title' => $g->title, 'meta' => $g->module.' · '.tf($g->line_count === 1 ? ':lines line' : ':lines lines', ['lines' => $g->line_count]),
            'summary' => $q !== '' ? $this->snippet($g->body, $q, (string) $g->summary) : (string) $g->summary,
            'url' => $this->url().'?doc='.$g->getKey(), 'data' => ['kind' => $g->module],
        ])->all();

        $filters = [['label' => 'All']];
        foreach (array_values(array_unique(array_column(array_column($posts, 'data'), 'kind'))) as $k) {
            $filters[] = ['label' => $k, 'where' => ['kind' => $k]];
        }

        return ['posts' => $posts, 'filters' => $filters, 'source' => $this->sourceText()];
    }

    // Satu panduan penuh; bagian rumus ditambahkan hanya untuk yang berhak.
    public function article(int $id): ?array
    {
        $g = SysGuide::query()->find($id);
        if (! $g) {
            return null;
        }
        $body = $g->body_secret && FormulaAccess::canSee() ? $g->body."\n\n## ".t('Formulas (owner only)')."\n\n".$g->body_secret : $g->body;

        return ['id' => $g->getKey(), 'title' => $g->title, 'meta' => $g->module.' · '.tf($g->line_count === 1 ? ':lines line' : ':lines lines', ['lines' => $g->line_count]), 'body' => $this->withoutTitle($body),
            'data' => ['kind' => $g->module]];
    }

    // Judul H1 sudah menjadi judul kartu.
    private function withoutTitle(string $body): string
    {
        return preg_replace('/\A\s*#\s[^\n]*\n/', '', $body, 1) ?? $body;
    }

    // Cuplikan sekitar kata yang dicari; tanpa kecocokan persis kembali ke ringkasan.
    private function snippet(string $body, string $q, string $fallback): string
    {
        $text = preg_replace(['/[#*`|>_]+/', '/\s+/'], ' ', $body) ?? $body;
        $pos = mb_stripos($text, $q);
        if ($pos === false) {
            return $fallback;
        }
        $from = max(0, $pos - 140);

        return ($from > 0 ? '… ' : '').trim(mb_substr($text, $from, 320)).' …';
    }
}
