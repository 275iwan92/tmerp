<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdToolingAsset;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Tooling master (plate sets, dies): the tooling gate of scheduling reads Status, SetupTimeEngine reads the first / repeat setup minutes.
// Mounting minutes are ASSUMED until the learning engine has real jobs; the operator timer screen records the actual ones.
class ToolingLookup extends DbLookup
{
    public function slug(): string
    {
        return 'prod-tooling';
    }

    public function title(): string
    {
        return t('Tooling Master');
    }

    public function module(): string
    {
        return 'PROD';
    }

    public function moduleLabel(): string
    {
        return 'Production';
    }

    public function guide(): ?string
    {
        return 'Plate sets and dies by dieline reference. Status Ready or In use lets scheduling plan the job; Not made, In making or Needs repair blocks it. '
            .'Setup minutes: first run mounts a new tool, repeat run reuses it. A tool that is not registered here does not block the job.';
    }

    protected function query(): Builder
    {
        return ProdToolingAsset::query()->orderBy('tooling_type')->orderBy('tooling_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'tooling_code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '140px'],
            ['key' => 'tooling_type', 'label' => 'Type', 'type' => 'select', 'options' => array_map('t', ProdToolingAsset::TYPES), 'required' => true, 'width' => '130px'],
            ['key' => 'dieline_ref', 'label' => 'Dieline Reference', 'required' => true, 'width' => '170px'],
            ['key' => 'size_note', 'label' => 'Size', 'width' => '150px'],
            ['key' => 'location', 'label' => 'Location', 'width' => '140px'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_map('t', ProdToolingAsset::STATUSES), 'required' => true, 'width' => '130px'],
            ['key' => 'impressions_planned', 'label' => 'Planned Impressions', 'type' => 'number', 'decimals' => 0, 'width' => '120px'],
            ['key' => 'impressions_used', 'label' => 'Used Impressions', 'type' => 'number', 'decimals' => 0, 'width' => '120px'],
            ['key' => 'setup_first_min', 'label' => 'Setup First Run (min)', 'type' => 'number', 'decimals' => 0, 'width' => '120px'],
            ['key' => 'setup_repeat_min', 'label' => 'Setup Repeat Run (min)', 'type' => 'number', 'decimals' => 0, 'width' => '120px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function prepare(array $in): array
    {
        foreach (['tooling_code', 'dieline_ref'] as $k) {
            if (isset($in[$k]) && is_string($in[$k])) {
                $in[$k] = strtoupper(trim($in[$k]));
            }
        }
        if (($in['impressions_used'] ?? '') === '' || ($in['impressions_used'] ?? null) === null) {
            $in['impressions_used'] = 0;
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['tooling_type' => [Rule::in(array_keys(ProdToolingAsset::TYPES))], 'status' => [Rule::in(array_keys(ProdToolingAsset::STATUSES))],
            'setup_first_min' => ['integer', 'min:0', 'max:65535'], 'setup_repeat_min' => ['integer', 'min:0', 'max:65535']];
    }
}
