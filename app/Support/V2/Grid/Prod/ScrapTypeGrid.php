<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Prd\PrdScrapType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Kelola Tipe Scrap (v1: pages::production.scrap-type).
class ScrapTypeGrid extends ProdGrid
{
    public function slug(): string
    {
        return 'prod-scrap';
    }

    public function title(): string
    {
        return 'Kelola Tipe Scrap';
    }

    protected function v1Route(): ?string
    {
        return 'production.scrap-type';
    }

    protected function v1Component(): ?string
    {
        return 'pages::production.scrap-type';
    }

    public function search(): string
    {
        return 'Cari kode, nama tipe...';
    }

    protected function query(): Builder
    {
        return PrdScrapType::query()->orderBy('category')->orderBy('type_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Tipe Scrap', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'category', 'label' => 'Kategori'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['photo' => null, 'name' => $m->type_name, 'code' => $m->type_code, 'category' => $m->category];
    }

    public function filters(): array
    {
        return ['category' => 'Kategori'];
    }
}
