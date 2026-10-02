<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Master\WhWarehouse;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Inventory > Warehouse > Buildings (tm2mst.wh_warehouse): satu baris = satu GEDUNG gudang (user 2026-10-02: A5, A6, A10 dan
// gedung lain; perusahaan lain cukup menambah baris). Ukuran dalam meter, bentuk kotak / huruf L / garis luar sendiri,
// 1-3 lantai -- dari sini Warehouse Map digambar. Area dan bin gedung itu diisi di Storage Areas dan Bins.
class BuildingLookup extends DbLookup
{
    public function slug(): string
    {
        return 'wh-buildings';
    }

    public function title(): string
    {
        return 'Buildings';
    }

    public function module(): string
    {
        return 'INV';
    }

    public function moduleLabel(): string
    {
        return 'Inventory';
    }

    public function group(): string
    {
        return 'Warehouse';
    }

    public function icon(): string
    {
        return 'fa-solid fa-building';
    }

    public function guide(): ?string
    {
        return 'One row per warehouse building. Length and Width are in metres. L shape: also fill the cut-out corner (length, width, which corner). '
            .'Custom outline: type the corner points in metres, clockwise, like 0,0; 30,0; 30,12; 0,12.';
    }

    protected function query(): Builder
    {
        return WhWarehouse::query()->orderBy('warehouse_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'warehouse_code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '120px'],
            ['key' => 'warehouse_name', 'label' => 'Building Name', 'required' => true, 'width' => '240px'],
            ['key' => 'warehouse_type', 'label' => 'Stock Kept', 'type' => 'select', 'options' => ['MIX' => 'Mixed', 'RM' => 'Raw Material', 'SFG' => 'Semi Finished Goods', 'FG' => 'Finished Goods', 'WASTE' => 'Waste', 'TRANSIT' => 'Transit'], 'required' => true, 'width' => '170px'],
            ['key' => 'length_m', 'label' => 'Length (m)', 'type' => 'number', 'decimals' => 2, 'width' => '110px'],
            ['key' => 'width_m', 'label' => 'Width (m)', 'type' => 'number', 'decimals' => 2, 'width' => '110px'],
            ['key' => 'floor_count', 'label' => 'Floors', 'type' => 'select', 'options' => [1 => '1', 2 => '2', 3 => '3'], 'required' => true, 'width' => '90px'],
            ['key' => 'shape', 'label' => 'Shape', 'type' => 'select', 'options' => WhWarehouse::SHAPES, 'required' => true, 'width' => '150px'],
            ['key' => 'notch_length_m', 'label' => 'Cut-out Length (m)', 'type' => 'number', 'decimals' => 2, 'width' => '150px'],
            ['key' => 'notch_width_m', 'label' => 'Cut-out Width (m)', 'type' => 'number', 'decimals' => 2, 'width' => '150px'],
            ['key' => 'notch_corner', 'label' => 'Cut-out Corner', 'type' => 'select', 'options' => WhWarehouse::CORNERS, 'width' => '140px'],
            ['key' => 'outline', 'label' => 'Custom Outline (m)', 'width' => '320px'],
            ['key' => 'address', 'label' => 'Address', 'width' => '300px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        $in['warehouse_code'] = strtoupper(trim((string) ($in['warehouse_code'] ?? '')));
        $in['floor_count'] = ($in['floor_count'] ?? '') === '' ? 1 : $in['floor_count'];
        $in['shape'] = ($in['shape'] ?? '') === '' ? 'RECT' : $in['shape'];
        $in['warehouse_type'] = ($in['warehouse_type'] ?? '') === '' ? 'MIX' : $in['warehouse_type'];

        return $in;
    }

    protected function rules(): array
    {
        return ['warehouse_code' => ['string', 'max:20', 'regex:/^[A-Z0-9_\-]+$/'], 'warehouse_name' => ['string', 'max:100'],
            'warehouse_type' => [Rule::in(['MIX', 'RM', 'SFG', 'FG', 'WASTE', 'TRANSIT'])], 'length_m' => ['min:1', 'max:2000'], 'width_m' => ['min:1', 'max:2000'],
            'floor_count' => ['integer', 'min:1', 'max:'.WhWarehouse::MAX_FLOORS], 'shape' => [Rule::in(array_keys(WhWarehouse::SHAPES))],
            'notch_length_m' => ['min:0'], 'notch_width_m' => ['min:0'], 'notch_corner' => [Rule::in(array_keys(WhWarehouse::CORNERS))],
            'outline' => ['nullable', 'string', 'max:500'], 'address' => ['nullable', 'string', 'max:255']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $shape = $in['shape'] ?? 'RECT';
        if ($shape === 'L') {
            $l = (float) ($in['length_m'] ?? 0);
            $w = (float) ($in['width_m'] ?? 0);
            $nl = (float) ($in['notch_length_m'] ?? 0);
            $nw = (float) ($in['notch_width_m'] ?? 0);
            if ($nl <= 0 || $nw <= 0 || $nl >= $l || $nw >= $w || empty($in['notch_corner'])) {
                return 'L shape: fill the cut-out length, width and corner; the cut-out must be smaller than the building.';
            }
        }
        if ($shape === 'CUSTOM' && (new WhWarehouse(['shape' => 'CUSTOM', 'outline' => $in['outline'] ?? '']))->outlinePoints() === []) {
            return 'Custom outline: type at least three points like 0,0; 30,0; 30,12; 0,12.';
        }

        return null;
    }
}
