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
        return 'Katalog Model Mesin';
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
            ['key' => 'alias_codes', 'label' => 'Nama Lain', 'width' => '150px'],
            ['key' => 'machine_type', 'label' => 'Jenis Mesin', 'required' => true, 'width' => '120px'],
            ['key' => 'drive', 'label' => 'Penggerak', 'type' => 'select', 'options' => ProdMachineModel::DRIVES, 'required' => true, 'width' => '100px'],
            ['key' => 'work_length_mm', 'label' => 'Ukuran Kerja P (mm)', 'type' => 'number', 'width' => '110px'],
            ['key' => 'work_width_mm', 'label' => 'Ukuran Kerja L (mm)', 'type' => 'number', 'width' => '110px'],
            ['key' => 'max_cut_length_mm', 'label' => 'Potong Maks P', 'type' => 'number', 'width' => '100px'],
            ['key' => 'max_cut_width_mm', 'label' => 'Potong Maks L', 'type' => 'number', 'width' => '100px'],
            ['key' => 'speed_per_hour', 'label' => 'Kecepatan / Jam', 'type' => 'number', 'width' => '110px'],
            ['key' => 'max_rule_length_m', 'label' => 'Pisau Maks (m)', 'type' => 'number', 'decimals' => 1, 'width' => '100px'],
            ['key' => 'motor_kw', 'label' => 'Motor (kW)', 'type' => 'number', 'decimals' => 1, 'width' => '90px'],
            ['key' => 'weight_kg', 'label' => 'Berat (kg)', 'type' => 'number', 'width' => '90px'],
            ['key' => 'pressure_ton', 'label' => 'Tekanan (ton)', 'type' => 'number', 'width' => '100px'],
            ['key' => 'maker_hint', 'label' => 'Pabrikan', 'width' => '200px'],
            ['key' => 'activate', 'label' => 'Aktif', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['drive' => [Rule::in(array_keys(ProdMachineModel::DRIVES))]];
    }
}
