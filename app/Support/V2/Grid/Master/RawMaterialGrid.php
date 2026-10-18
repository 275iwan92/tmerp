<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\MatRaw;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master Raw Material: semua item bahan baku dengan kategori, tipe, merek, satuan stok, reorder point, stok dan grammage; Open dan + New Record membuka form v2 md-raw-form.
 * Sumber hulu  : tm2mst.mat_raw (11 ribu+ baris, semua dikirim; kolom dibuat ramping).
 * Input        : Pencarian, filter Category / Item Type / Unit.
 * Output       : Baris grid.
 * Dipakai oleh : Menu MD_RAW / PRE_MD_RAW / INV_REF_RAW (v2:md-raw), layar md-raw-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::master.raw-material.* sudah dipindah ke tmerp_unused (aturan D.25).
 */
class RawMaterialGrid extends MasterDataGrid
{
    protected int $limit = 50000;

    public function slug(): string
    {
        return 'md-raw';
    }

    public function title(): string
    {
        return t('Master Raw Material');
    }

    protected function canCreate(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'md-raw-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'md-raw-form').'?new=1';
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
