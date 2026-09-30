<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\WhWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Master Gudang (v1: pages::master.warehouse.index + entry).
class WarehouseGrid extends MasterDataGrid
{
    public function slug(): string
    {
        return 'md-warehouse';
    }

    public function title(): string
    {
        return 'Master Gudang';
    }

    protected function v1Route(): ?string
    {
        return 'master.warehouse.list';
    }

    protected function v1Component(): ?string
    {
        return 'pages::master.warehouse.index';
    }

    public function openUrl(): ?string
    {
        return $this->routeUrl('master.warehouse.edit', 'warehouse');
    }

    public function newUrl(): ?string
    {
        return route('master.warehouse.create');
    }

    public function search(): string
    {
        return 'Cari kode, nama gudang...';
    }

    protected function query(): Builder
    {
        return WhWarehouse::query()->withCount('locations')->orderBy('warehouse_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Warehouse', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'type', 'label' => 'Tipe'],
            ['key' => 'locations', 'label' => 'Lokasi', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['photo' => null, 'name' => $m->warehouse_name, 'code' => $m->warehouse_code, 'type' => $m->warehouse_type, 'locations' => (int) $m->locations_count];
    }

    public function filters(): array
    {
        return ['type' => 'Tipe'];
    }
}
