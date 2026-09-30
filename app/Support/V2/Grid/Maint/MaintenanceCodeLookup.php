<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Prd\MntCode;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Katalog kode Maintenance (Component / Problem / Cause / Action) -- Grid Lookup: kepala maintenance menambah/mengubah langsung di sel.
// Dipakai form Work Order (tab Failure) & laporan Pareto kerusakan. Nama English, bahasa lain lewat kamus.
class MaintenanceCodeLookup extends DbLookup
{
    public function slug(): string
    {
        return 'maintenance-codes';
    }

    public function title(): string
    {
        return 'Maintenance Codes';
    }

    public function module(): string
    {
        return 'MAINT';
    }

    public function moduleLabel(): string
    {
        return 'Maintenance';
    }

    protected function query(): Builder
    {
        return MntCode::query()->orderByRaw("FIELD(code_type, 'COMPONENT', 'PROBLEM', 'CAUSE', 'ACTION')")->orderBy('sort_order')->orderBy('name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'code_type', 'label' => 'Type', 'type' => 'select', 'options' => MntCode::TYPES, 'required' => true, 'width' => '120px'],
            ['key' => 'code', 'label' => 'Code', 'required' => true, 'case' => 'uppercase', 'width' => '150px'],
            ['key' => 'name', 'label' => 'Name', 'required' => true, 'width' => '260px'],
            ['key' => 'machine_type', 'label' => 'Machine Type (blank = all)', 'width' => '180px'],
            ['key' => 'sort_order', 'label' => 'Sort', 'type' => 'number', 'width' => '80px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['code_type' => [Rule::in(array_keys(MntCode::TYPES))]];
    }
}
