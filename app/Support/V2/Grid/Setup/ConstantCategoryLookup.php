<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysConstantCategory;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;

// Setup > Constant Categories: kelompok baris Constants (sys_constant_category). Dulu hanya bisa ditambah lewat migration
// (backlog01 UI-18); sekarang diisi di sel. Kode yang sudah tersimpan terkunci karena dirujuk sys_constant.category_code.
class ConstantCategoryLookup extends DbLookup
{
    public function slug(): string
    {
        return 'setup-constant-categories';
    }

    public function title(): string
    {
        return 'Constant Categories';
    }

    public function module(): string
    {
        return 'SETUP';
    }

    public function moduleLabel(): string
    {
        return 'Setup';
    }

    public function group(): string
    {
        return 'Constants';
    }

    public function icon(): string
    {
        return 'fa-solid fa-folder-tree';
    }

    public function guide(): ?string
    {
        return 'Groups for the rows of Constants. A saved code cannot be changed because constants refer to it.';
    }

    protected function query(): Builder
    {
        return SysConstantCategory::query()->orderBy('sort_order')->orderBy('code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '180px'],
            ['key' => 'label', 'label' => 'Label', 'required' => true, 'width' => '260px'],
            ['key' => 'description', 'label' => 'Description', 'width' => '420px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'decimals' => 0, 'width' => '90px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        if (isset($in['code']) && is_string($in['code'])) {
            $in['code'] = strtoupper(trim($in['code']));
        }
        if (($in['sort_order'] ?? '') === '' || ($in['sort_order'] ?? null) === null) {
            $in['sort_order'] = 100;
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['code' => ['string', 'max:30', 'regex:/^[A-Z0-9_]+$/'], 'label' => ['string', 'max:100'], 'description' => ['string', 'max:255'],
            'sort_order' => ['integer', 'between:0,99999']];
    }
}
