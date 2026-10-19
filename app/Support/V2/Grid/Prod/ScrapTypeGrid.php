<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Prd\PrdScrapType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master Tipe Scrap (Reject / Waste) dengan kategori; Open dan + New Record membuka form v2 prod-scrap-form.
 * Sumber hulu  : tm2prd.prd_scrap_type.
 * Input        : Pencarian, filter Category.
 * Output       : Baris grid; tombol aktif / nonaktif membalik prd_scrap_type.activate.
 * Dipakai oleh : Menu Production (v2:prod-scrap), layar prod-scrap-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::production.scrap-type sudah dipindah ke tmerp_unused (aturan D.25, Master T6b).
 */
class ScrapTypeGrid extends ProdGrid
{
    public function slug(): string
    {
        return 'prod-scrap';
    }

    public function title(): string
    {
        return t('Scrap Types');
    }

    protected function canCreate(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'prod-scrap-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'prod-scrap-form').'?new=1';
    }

    public function search(): string
    {
        return 'Search code, type name...';
    }

    protected function query(): Builder
    {
        return PrdScrapType::query()->orderBy('category')->orderBy('type_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Scrap Type', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'category', 'label' => 'Category'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['photo' => null, 'name' => $m->type_name, 'code' => $m->type_code, 'category' => $m->category];
    }

    public function filters(): array
    {
        return ['category' => 'Category'];
    }
}
