<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Master\ProdMarket;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master Product Market (Kategori Produk di Inquiry New): kategori dan detail kategori dengan foto, induk, Tipe Produk, warna awal dan jumlah ukuran standar; Open dan + New Record membuka form v2 sales-market-form.
 * Sumber hulu  : tm2mst.prod_market (+ prod_type, prod_type_size untuk jumlah ukuran).
 * Input        : Pencarian, filter Product Type / Parent.
 * Output       : Baris grid.
 * Dipakai oleh : Menu SALES_PMKT (v2:sales-product-market), layar sales-market-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::lookup.product-market* sudah dipindah ke tmerp_unused (aturan D.25).
 */
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

    protected function canCreate(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'sales-market-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'sales-market-form').'?new=1';
    }

    public function search(): string
    {
        return 'Search product market, product type...';
    }

    protected function query(): Builder
    {
        return ProdMarket::query()->with(['type', 'parent'])->withCount('sizes')->orderBy('market_label');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => 'Photo', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Product Market', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'parent', 'label' => 'Parent'],
            ['key' => 'type', 'label' => 'Product Type'],
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
        return ['type' => 'Product Type', 'parent' => 'Parent'];
    }
}
