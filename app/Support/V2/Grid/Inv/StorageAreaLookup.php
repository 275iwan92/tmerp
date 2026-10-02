<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Inventory > Warehouse > Storage Areas (tm2mst.wh_zone): area penyimpanan di satu lantai gedung (user 2026-10-02: "pastikan
// bisa ditambah area storage"). Posisi dan ukuran dalam meter dari sudut kiri-atas denah; bisa juga digeser di Warehouse Map
// (Edit Layout). Bin area ini dibuat lewat Generate Bins di Warehouse Map atau diketik di Bins.
class StorageAreaLookup extends DbLookup
{
    public function slug(): string
    {
        return 'wh-zones';
    }

    public function title(): string
    {
        return 'Storage Areas';
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
        return 'fa-solid fa-vector-square';
    }

    public function guide(): ?string
    {
        return 'One row per storage area on one floor of a building. X and Y are the top-left corner on the floor plan, Width and Depth its size, all in metres. '
            .'A saved code cannot be changed because bin codes are made from it.';
    }

    protected function query(): Builder
    {
        return WhZone::query()->orderBy('warehouse_id')->orderBy('floor_no')->orderBy('sort_order')->orderBy('zone_code');
    }

    protected function models(): array
    {
        return [WhZone::class, WhWarehouse::class];
    }

    public function columns(): array
    {
        return [
            ['key' => 'warehouse_id', 'label' => 'Building', 'type' => 'select', 'options' => WhWarehouse::query()->orderBy('warehouse_code')->get()
                ->mapWithKeys(fn (WhWarehouse $w) => [$w->id_warehouse => $w->warehouse_code.' · '.$w->warehouse_name])->all(), 'required' => true, 'lockSaved' => true, 'width' => '210px'],
            ['key' => 'floor_no', 'label' => 'Floor', 'type' => 'select', 'options' => [1 => '1', 2 => '2', 3 => '3'], 'required' => true, 'width' => '80px'],
            ['key' => 'zone_code', 'label' => 'Code', 'required' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '100px'],
            ['key' => 'zone_name', 'label' => 'Area Name', 'required' => true, 'width' => '220px'],
            ['key' => 'zone_type', 'label' => 'Use', 'type' => 'select', 'options' => WhZone::TYPES, 'required' => true, 'width' => '190px'],
            ['key' => 'storage_mode', 'label' => 'How Stored', 'type' => 'select', 'options' => WhZone::MODES, 'required' => true, 'width' => '180px'],
            ['key' => 'stock_class', 'label' => 'Stock Class', 'type' => 'select', 'options' => WhZone::STOCK_CLASSES, 'required' => true, 'width' => '170px'],
            ['key' => 'map_x', 'label' => 'X (m)', 'type' => 'number', 'decimals' => 2, 'width' => '90px'],
            ['key' => 'map_y', 'label' => 'Y (m)', 'type' => 'number', 'decimals' => 2, 'width' => '90px'],
            ['key' => 'map_w', 'label' => 'Width (m)', 'type' => 'number', 'decimals' => 2, 'width' => '100px'],
            ['key' => 'map_h', 'label' => 'Depth (m)', 'type' => 'number', 'decimals' => 2, 'width' => '100px'],
            // Setinggi apa barang boleh ditumpuk di area ini; kosong = tinggi bersih lantainya. Luas x tinggi tumpuk = volume simpan.
            ['key' => 'stack_height_m', 'label' => 'Stack Height (m)', 'type' => 'number', 'decimals' => 2, 'width' => '140px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'decimals' => 0, 'width' => '80px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        $in['zone_code'] = strtoupper(trim((string) ($in['zone_code'] ?? '')));
        foreach (['floor_no' => 1, 'zone_type' => 'STORAGE', 'storage_mode' => 'FLOOR', 'stock_class' => 'ANY', 'map_x' => 0, 'map_y' => 0, 'map_w' => 5, 'map_h' => 5, 'sort_order' => 0] as $k => $default) {
            if (($in[$k] ?? '') === '' || ($in[$k] ?? null) === null) {
                $in[$k] = $default;
            }
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['warehouse_id' => ['integer', Rule::exists(WhWarehouse::class, 'id_warehouse')], 'floor_no' => ['integer', 'min:1', 'max:'.WhWarehouse::MAX_FLOORS],
            // Paling panjang 6 huruf: kode area menjadi bagian kode bin dan kolom wh_loc.row_code (10 huruf).
            'zone_code' => ['string', 'max:6', 'regex:/^[A-Z0-9]+$/'], 'zone_name' => ['string', 'max:100'], 'zone_type' => [Rule::in(array_keys(WhZone::TYPES))],
            'storage_mode' => [Rule::in(array_keys(WhZone::MODES))], 'stock_class' => [Rule::in(array_keys(WhZone::STOCK_CLASSES))],
            'map_x' => ['min:0'], 'map_y' => ['min:0'], 'map_w' => ['min:1'], 'map_h' => ['min:1'], 'stack_height_m' => ['min:0', 'max:50'],
            'sort_order' => ['integer', 'min:0', 'max:65535']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $zone = $id ? WhZone::query()->find($id) : null;
        $whId = (int) ($zone?->warehouse_id ?? $in['warehouse_id'] ?? 0);
        $code = $zone?->zone_code ?? $in['zone_code'];
        $wh = WhWarehouse::query()->find($whId);
        if (! $wh) {
            return 'Choose the building.';
        }
        if (WhZone::query()->where('warehouse_id', $whId)->where('zone_code', $code)->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
            return 'This code is already used in that building.';
        }
        if ((int) $in['floor_no'] > $wh->floors()) {
            return 'That building does not have this floor. Raise Floors in Buildings first.';
        }
        $ceiling = $wh->floorHeight((int) $in['floor_no']);
        if ($ceiling && (float) ($in['stack_height_m'] ?? 0) > $ceiling + 0.001) {
            return 'The stack height is higher than the clear height of that floor.';
        }
        [$maxX, $maxY] = $wh->extent();
        if ($maxX > 0 && ((float) $in['map_x'] + (float) $in['map_w'] > $maxX + 0.001 || (float) $in['map_y'] + (float) $in['map_h'] > $maxY + 0.001)) {
            return 'The area does not fit inside the building: X + Width and Y + Depth must stay within the building size.';
        }

        return null;
    }
}
