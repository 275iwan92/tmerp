<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdMachineModel;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Katalog Model Mesin di pasar (tm2mst.prod_machine_model) — Grid Lookup: user menambah/mengubah model langsung di sel;
// Master Mesin memilih salah satunya.
class MachineModelLookup extends DbLookup
{
    public function slug(): string
    {
        return 'prod-machine-model';
    }

    public function title(): string
    {
        return t('Machine Model Catalog');
    }

    public function module(): string
    {
        return 'PROD';
    }

    public function moduleLabel(): string
    {
        return 'Production';
    }

    protected function query(): Builder
    {
        return ProdMachineModel::query()->orderBy('machine_type')->orderBy('drive')->orderBy('work_length_mm');
    }

    public function columns(): array
    {
        return [
            ['key' => 'model_code', 'label' => 'Model', 'required' => true, 'unique' => true, 'case' => 'uppercase', 'width' => '110px'],
            ['key' => 'alias_codes', 'label' => 'Alias', 'width' => '150px'],
            ['key' => 'machine_type', 'label' => 'Machine Type', 'required' => true, 'width' => '120px'],
            ['key' => 'drive', 'label' => 'Drive', 'type' => 'select', 'options' => ProdMachineModel::DRIVES, 'required' => true, 'width' => '100px'],
            ['key' => 'work_length_mm', 'label' => 'Working Size L (mm)', 'type' => 'number', 'width' => '110px'],
            ['key' => 'work_width_mm', 'label' => 'Working Size W (mm)', 'type' => 'number', 'width' => '110px'],
            ['key' => 'max_cut_length_mm', 'label' => 'Max Cut L', 'type' => 'number', 'width' => '100px'],
            ['key' => 'max_cut_width_mm', 'label' => 'Max Cut W', 'type' => 'number', 'width' => '100px'],
            ['key' => 'speed_per_hour', 'label' => 'Speed / Hour', 'type' => 'number', 'width' => '110px'],
            ['key' => 'max_rule_length_m', 'label' => 'Max Knife (m)', 'type' => 'number', 'decimals' => 1, 'width' => '100px'],
            ['key' => 'motor_kw', 'label' => 'Motor (kW)', 'type' => 'number', 'decimals' => 1, 'width' => '90px'],
            ['key' => 'weight_kg', 'label' => 'Weight (kg)', 'type' => 'number', 'width' => '90px'],
            ['key' => 'pressure_ton', 'label' => 'Pressure (ton)', 'type' => 'number', 'width' => '100px'],
            ['key' => 'maker_hint', 'label' => 'Manufacturer', 'width' => '200px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['drive' => [Rule::in(array_keys(ProdMachineModel::DRIVES))]];
    }
}
