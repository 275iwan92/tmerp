<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Master Kategori Bahan Baku (v1: pages::master.mat-category, pohon 5 level). Grid = daftar rata + kolom Level & Induk.
class MatCategoryGrid extends MasterDataGrid
{
    private array $materials = [];

    public function slug(): string
    {
        return 'md-matcat';
    }

    public function title(): string
    {
        return 'Master Kategori Bahan Baku';
    }

    protected function v1Route(): ?string
    {
        return 'master.mat-category';
    }

    protected function v1Component(): ?string
    {
        return 'pages::master.mat-category';
    }

    protected function models(): array
    {
        return [MatCategory::class, MatRaw::class];
    }

    public function search(): string
    {
        return 'Cari kategori, induk...';
    }

    protected function query(): Builder
    {
        return MatCategory::query()->with('parent')->orderBy('level')->orderBy('category_name');
    }

    protected function prefetch(Collection $items): void
    {
        // mat_raw.category1 menyimpan NAMA kategori level 1 (bukan id).
        $this->materials = MatRaw::query()->whereIn('category1', $items->where('level', 1)->pluck('category_name'))->groupBy('category1')
            ->selectRaw('category1, COUNT(*) n')->pluck('n', 'category1')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Kategori', 'type' => 'identity', 'code' => 'prefix', 'avatar' => false],
            ['key' => 'level', 'label' => 'Level', 'type' => 'number'],
            ['key' => 'parent', 'label' => 'Induk'],
            ['key' => 'class', 'label' => 'Kelas Stok', 'type' => 'badge', 'tones' => ['RM' => 'info', 'FG' => 'success', 'SFG' => 'warn']],
            ['key' => 'materials', 'label' => 'Materials (level 1)', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['photo' => null, 'name' => $m->category_name, 'prefix' => $m->prefix_char, 'level' => (int) $m->level,
            'parent' => $m->parent?->category_name, 'class' => $m->stock_class, 'materials' => (int) ($m->level == 1 ? ($this->materials[$m->category_name] ?? 0) : 0)];
    }

    public function filters(): array
    {
        return ['level' => 'Level', 'class' => 'Kelas Stok', 'parent' => 'Induk'];
    }
}
