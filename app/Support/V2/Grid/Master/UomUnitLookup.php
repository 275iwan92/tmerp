<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\UomUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

// Master Satuan (v1: pages::master.uom, daftar satuan). Satu satuan dasar per dimensi; konversi antar satuan = UomConversionLookup.
class UomUnitLookup extends MasterDataLookup
{
    // Nilai tersimpan masih istilah lama (utang konversi data, docs/multilanguage_design.md); label tampil English lewat kamus.
    public const DIMENSIONS = ['PANJANG' => 'Length', 'LUAS' => 'Area', 'MASSA' => 'Mass', 'VOLUME' => 'Volume', 'JUMLAH' => 'Count',
        'WAKTU' => 'Time', 'SPEC' => 'Specification'];

    public function slug(): string
    {
        return 'md-uom';
    }

    public function title(): string
    {
        return 'Units of Measure';
    }

    public function icon(): string
    {
        return 'fa-solid fa-ruler';
    }

    public function guide(): ?string
    {
        return 'One base unit per dimension: ticking Base on a unit clears it on the other units of the same dimension. '
            .'Dimension Specification (gsm, micron, ...) has no base unit and no conversion. A saved code cannot be changed, only deactivated. '
            .'Conversions between units are in Unit Conversion.';
    }

    protected function query(): Builder
    {
        return UomUnit::query()->orderByRaw('dimension IS NULL')->orderBy('dimension')->orderBy('uom_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'uom_code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '130px'],
            ['key' => 'uom_name', 'label' => 'Unit Name', 'required' => true, 'width' => '280px'],
            // Wajib saat barisnya disimpan (rules), bukan 'required' di layar: satuan lama tanpa dimensi akan memblokir Save baris lain.
            ['key' => 'dimension', 'label' => 'Dimension', 'type' => 'select', 'options' => self::DIMENSIONS, 'width' => '150px'],
            ['key' => 'is_base', 'label' => 'Base', 'type' => 'check', 'width' => '80px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        return $this->upper($in, 'uom_code');
    }

    protected function rules(): array
    {
        return ['uom_code' => ['string', 'max:50'], 'uom_name' => ['string', 'max:200'], 'dimension' => ['required', Rule::in(array_keys(self::DIMENSIONS))]];
    }

    protected function check(array $in, ?int $id): ?string
    {
        return ($in['dimension'] ?? null) === 'SPEC' && ! empty($in['is_base']) ? 'Dimension Specification cannot have a base unit.' : null;
    }

    protected function saved(Model $row): void
    {
        if ($row->is_base) {
            UomUnit::query()->where('dimension', $row->dimension)->whereKeyNot($row->getKey())->where('is_base', true)->update(['is_base' => false]);
        }
    }
}
