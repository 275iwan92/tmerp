<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\MatRaw;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Master Bahan Baku (v1: pages::master.raw-material.index + entry).
class RawMaterialGrid extends MasterDataGrid
{
    // 11 ribu+ baris: semua dikirim (tanpa potong diam-diam); kolom dibuat ramping.
    protected int $limit = 50000;

    public function slug(): string
    {
        return 'md-raw';
    }

    public function title(): string
    {
        return t('Raw Material Master Data');
    }

    protected function v1Route(): ?string
    {
        return 'master.raw-material.list';
    }

    protected function v1Component(): ?string
    {
        return 'pages::master.raw-material.index';
    }

    public function openUrl(): ?string
    {
        return $this->routeUrl('master.raw-material.edit', 'material');
    }

    public function newUrl(): ?string
    {
        return route('master.raw-material.create');
    }

    protected function models(): array
    {
        return [MatRaw::class];
    }

    public function search(): string
    {
        return 'Search code, name, brand...';
    }

    protected function query(): Builder
    {
        return MatRaw::query()->orderBy('material_name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Raw Material', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'type', 'label' => 'Item Type'],
            ['key' => 'brand', 'label' => 'Brand'],
            ['key' => 'uom', 'label' => 'Stock Unit'],
            ['key' => 'reorder', 'label' => 'Reorder Point', 'type' => 'number'],
            ['key' => 'stock', 'label' => 'Stock on hand', 'type' => 'link', 'format' => 'number'],
            ['key' => 'gsm', 'label' => 'Grammage', 'type' => 'number', 'visible' => false],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'photo' => null, 'name' => $m->material_name, 'code' => $m->material_code,
            'category' => $m->category1 ?: null, 'type' => $m->item_type, 'brand' => $m->brand,
            'uom' => $m->uom_stock, 'reorder' => (float) $m->reorder_point, 'stock' => (float) $m->cur_stock, 'gsm' => (float) $m->gram_weight,
        ];
    }

    public function filters(): array
    {
        return ['category' => 'Category', 'type' => 'Item Type', 'uom' => 'Unit'];
    }
}
