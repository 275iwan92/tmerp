<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Inventory > Warehouse > Bins (tm2mst.wh_loc): tempat stok menempel. Biasanya dibuat lewat Generate Bins di Warehouse Map;
// di sini kapasitas, area, dan blokir tiap bin diubah, atau bin tunggal diketik sendiri. Kode bin yang sudah tersimpan
// tidak bisa diubah (kartu stok dan label merujuk ke situ).
class BinLookup extends DbLookup
{
    public function slug(): string
    {
        return 'wh-bins';
    }

    public function title(): string
    {
        return 'Bins';
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
        return 'fa-solid fa-table-cells';
    }

    public function guide(): ?string
    {
        return 'Bins are usually made with Generate Bins on the Warehouse Map. Here you set the capacity, the storage area and Usable (untick to block a bin, with the reason). '
            .'Row, Column and Level together must be unique within a building.';
    }

    protected function query(): Builder
    {
        return WhLoc::query()->orderBy('warehouse_id')->orderBy('location_code');
    }

    protected function models(): array
    {
        return [WhLoc::class, WhZone::class, WhWarehouse::class];
    }

    public function columns(): array
    {
        $buildings = WhWarehouse::query()->orderBy('warehouse_code')->pluck('warehouse_code', 'id_warehouse');

        return [
            ['key' => 'warehouse_id', 'label' => 'Building', 'type' => 'select', 'options' => $buildings->all(), 'required' => true, 'lockSaved' => true, 'width' => '120px'],
            ['key' => 'zone_id', 'label' => 'Storage Area', 'type' => 'select', 'options' => WhZone::query()->orderBy('warehouse_id')->orderBy('zone_code')->get()
                ->mapWithKeys(fn (WhZone $z) => [$z->id_zone => ($buildings[$z->warehouse_id] ?? '?').' · '.$z->zone_code.' · '.$z->zone_name])->all(), 'width' => '250px'],
            ['key' => 'location_code', 'label' => 'Bin Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '190px'],
            ['key' => 'location_name', 'label' => 'Short Name', 'width' => '150px'],
            ['key' => 'row_code', 'label' => 'Row', 'required' => true, 'width' => '100px'],
            ['key' => 'col_code', 'label' => 'Column', 'required' => true, 'width' => '80px'],
            ['key' => 'stack_code', 'label' => 'Level', 'required' => true, 'width' => '70px'],
            ['key' => 'capacity_kg', 'label' => 'Capacity (kg)', 'type' => 'number', 'decimals' => 0, 'width' => '120px'],
            ['key' => 'capacity_pallet', 'label' => 'Pallets', 'type' => 'number', 'decimals' => 0, 'width' => '90px'],
            ['key' => 'is_usable', 'label' => 'Usable', 'type' => 'check', 'width' => '80px'],
            ['key' => 'block_reason', 'label' => 'Blocked Because', 'width' => '240px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        $in['location_code'] = strtoupper(trim((string) ($in['location_code'] ?? '')));
        foreach (['row_code', 'col_code', 'stack_code'] as $k) {
            $in[$k] = strtoupper(trim((string) ($in[$k] ?? '')));
        }
        // Bin yang dipakai lagi tidak menyimpan alasan blokir lamanya.
        if (! empty($in['is_usable'])) {
            $in['block_reason'] = null;
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['warehouse_id' => ['integer', Rule::exists(WhWarehouse::class, 'id_warehouse')], 'zone_id' => ['nullable', 'integer', Rule::exists(WhZone::class, 'id_zone')],
            'location_code' => ['string', 'max:60', 'regex:/^[A-Z0-9_\-]+$/'], 'location_name' => ['nullable', 'string', 'max:100'],
            'row_code' => ['string', 'max:10'], 'col_code' => ['string', 'max:10'], 'stack_code' => ['string', 'max:10'],
            'capacity_kg' => ['min:0'], 'capacity_pallet' => ['integer', 'min:0', 'max:65535'], 'block_reason' => ['nullable', 'string', 'max:255']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $bin = $id ? WhLoc::query()->find($id) : null;
        $whId = (int) ($bin?->warehouse_id ?? $in['warehouse_id'] ?? 0);
        if (! empty($in['zone_id']) && (int) WhZone::query()->whereKey($in['zone_id'])->value('warehouse_id') !== $whId) {
            return 'The storage area belongs to another building.';
        }
        if (WhLoc::query()->where('warehouse_id', $whId)->where('row_code', $in['row_code'])->where('col_code', $in['col_code'])->where('stack_code', $in['stack_code'])
            ->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
            return 'Another bin of this building already has the same Row, Column and Level.';
        }
        if (empty($in['is_usable']) && trim((string) ($in['block_reason'] ?? '')) === '') {
            return 'A bin that is not usable needs the reason (Blocked Because).';
        }

        return null;
    }
}
