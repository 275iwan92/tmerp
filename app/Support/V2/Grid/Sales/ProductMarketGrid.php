<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Master\ProdMarket;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Master Product Market (v1: pages::lookup.product-market + entry) = Kategori Produk di Inquiry.
class ProductMarketGrid extends DbGrid
{
    public function slug(): string
    {
        return 'sales-product-market';
    }

    public function title(): string
    {
        return 'Master Product Market';
    }

    public function module(): string
    {
        return 'SALES';
    }

    public function moduleLabel(): string
    {
        return 'Sales';
    }

    public function family(): string
    {
        return self::MASTER;
    }

    protected function v1Route(): ?string
    {
        return 'lookup.product-market';
    }

    protected function v1Component(): ?string
    {
        return 'pages::lookup.product-market';
    }

    public function openUrl(): ?string
    {
        return $this->routeUrl('lookup.product-market.edit', 'market');
    }

    public function newUrl(): ?string
    {
        return route('lookup.product-market.create');
    }

    public function search(): string
    {
        return 'Cari product market, tipe produk...';
    }

    protected function query(): Builder
    {
        return ProdMarket::query()->with(['type', 'parent'])->withCount('sizes')->orderBy('market_label');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => 'Foto', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Product Market', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'parent', 'label' => 'Induk'],
            ['key' => 'type', 'label' => 'Tipe Produk'],
            ['key' => 'color', 'label' => 'Initial Colour'],
            ['key' => 'sizes', 'label' => 'Standard Size', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['photo' => $m->photoUrl(), 'name' => $m->market_label, 'code' => $m->market_code, 'parent' => $m->parent?->market_label,
            'type' => $m->type?->type_name, 'color' => $m->default_color, 'sizes' => (int) $m->sizes_count];
    }

    public function filters(): array
    {
        return ['type' => 'Tipe Produk', 'parent' => 'Induk'];
    }
}
