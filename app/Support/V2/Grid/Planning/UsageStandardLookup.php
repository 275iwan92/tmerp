<?php

namespace App\Support\V2\Grid\Planning;

use App\Models\Master\MatUsageStandard;
use App\Support\V2\Data\MaterialPlanning;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;

// Material Usage Standard: standar pemakaian bahan non-kertas untuk Material Planning (kertas dihitung dari layout).
class UsageStandardLookup extends DbLookup
{
    public const ROLES = ['INK', 'VARNISH', 'COATING', 'FILM', 'GLUE'];

    public const BASIS = ['PER_M2_FULL_COVERAGE' => 'per m2 at 100% coverage', 'PER_M2' => 'per m2 of sheet', 'PER_1000_PCS' => 'per 1000 pcs', 'PER_SHEET' => 'per sheet'];

    public function slug(): string
    {
        return 'usage-standard';
    }

    public function title(): string
    {
        return 'Material Usage Standard';
    }

    public function module(): string
    {
        return 'MDATA';
    }

    public function moduleLabel(): string
    {
        return 'Master Data';
    }

    public function icon(): string
    {
        return 'fa-solid fa-fill-drip';
    }

    public function guide(): ?string
    {
        return 'How much ink, varnish, coating, film and glue a job uses. Material Planning multiplies these by the sheet area or quantity of each order. '
            .'A standard (other than ink) applies to a part when one of its trigger words appears in the part process names or finishing. '
            .'The starting values are general offset-printing figures, not factory data: please correct them. Default Item = material_id of the stock item to book (Master Raw Material).';
    }

    protected function query(): Builder
    {
        return MatUsageStandard::query()->orderBy('usage_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'usage_code', 'label' => 'Code', 'required' => true, 'unique' => true, 'case' => 'uppercase', 'width' => '150px'],
            ['key' => 'usage_name', 'label' => 'Name', 'required' => true, 'width' => '200px'],
            ['key' => 'material_role', 'label' => 'Kind', 'type' => 'select', 'options' => self::ROLES, 'required' => true, 'width' => '110px'],
            ['key' => 'basis', 'label' => 'Basis', 'type' => 'select', 'options' => self::BASIS, 'required' => true, 'width' => '190px'],
            ['key' => 'qty_per_basis', 'label' => 'Qty per Basis', 'type' => 'number', 'required' => true, 'decimals' => 4, 'width' => '120px'],
            ['key' => 'uom', 'label' => 'UoM', 'required' => true, 'case' => 'uppercase', 'width' => '80px'],
            ['key' => 'trigger_keyword', 'label' => 'Trigger Words (comma)', 'width' => '200px'],
            ['key' => 'default_material_id', 'label' => 'Default Item (ID)', 'type' => 'number', 'decimals' => 0, 'width' => '130px'],
            ['key' => 'notes', 'label' => 'Notes', 'width' => '260px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function rules(): array
    {
        return ['qty_per_basis' => ['gt:0'], 'default_material_id' => ['nullable', 'integer', 'exists:tm2mst.mat_raw,material_id']];
    }

    public function save(array $changed): array
    {
        $rows = parent::save($changed);
        MaterialPlanning::changed();

        return $rows;
    }
}
