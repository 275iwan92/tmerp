<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SecModule;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;

// Setup > Security > Modules (user 2026-10-04): the permission units (tm2sys.sec_module). module_code must equal the module
// code in sys_app_menu so permissions and the menu stay in step; min_mgmt_level = lowest management level (1 STAFF .. 5 DIREKSI)
// that may open the module at all. Rights per group per module live in Groups on Modules.
class ModuleLookup extends DbLookup
{
    public function slug(): string
    {
        return 'security-modules';
    }

    public function title(): string
    {
        return 'Modules';
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
        return 'Security';
    }

    public function icon(): string
    {
        return 'fa-solid fa-cubes';
    }

    public function guide(): ?string
    {
        return 'One row per module that can be granted to a group. Code must match the module code of the main menu. Min level = lowest management level (1 Staff .. 5 Board) allowed to open it. Rights per group are set in Groups on Modules.';
    }

    protected function query(): Builder
    {
        return SecModule::query()->orderBy('sort_order')->orderBy('module_code');
    }

    public function columns(): array
    {
        $parents = SecModule::query()->whereNull('parent_id')->orderBy('sort_order')->pluck('module_name', 'id_module')->all();

        return [
            ['key' => 'module_code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'width' => '120px', 'case' => 'uppercase'],
            ['key' => 'module_name', 'label' => 'Module', 'required' => true, 'width' => '220px'],
            ['key' => 'parent_id', 'label' => 'Under', 'type' => 'select', 'options' => $parents, 'width' => '180px'],
            ['key' => 'icon', 'label' => 'Icon', 'width' => '160px'],
            ['key' => 'min_mgmt_level', 'label' => 'Min level', 'type' => 'number', 'width' => '90px'],
            ['key' => 'is_reportable', 'label' => 'Has reports', 'type' => 'check', 'width' => '90px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'width' => '70px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['module_code' => ['regex:/^[A-Z][A-Z0-9_]{1,29}$/'], 'min_mgmt_level' => ['integer', 'min:1', 'max:5'], 'sort_order' => ['integer', 'min:0'],
            'parent_id' => ['nullable', 'integer']];
    }

    protected function prepare(array $in): array
    {
        $in['module_code'] = strtoupper(trim((string) ($in['module_code'] ?? '')));
        $in['parent_id'] = ($in['parent_id'] ?? '') === '' ? null : (int) $in['parent_id'];

        return $in;
    }

    protected function check(array $in, ?int $id): ?string
    {
        if ($id && (int) ($in['parent_id'] ?? 0) === $id) {
            return t('A module cannot be placed under itself.');
        }

        return null;
    }
}
