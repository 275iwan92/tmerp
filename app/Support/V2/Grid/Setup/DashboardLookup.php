<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SecModule;
use App\Models\Sys\SysDashboard;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Setup > Opening Dashboards: daftar dashboard layar pembuka. OVERALL = papan yang disusun pemiliknya (tanda centang);
// yang lain = dashboard bidang, isinya template di Setup > Opening Board Templates. Kode dirujuk template dan katalog widget,
// jadi tidak bisa diganti setelah tersimpan.
class DashboardLookup extends DbLookup
{
    public function slug(): string
    {
        return 'setup-dashboards';
    }

    public function title(): string
    {
        return 'Opening Dashboards';
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
        return 'Settings';
    }

    public function icon(): string
    {
        return 'fa-solid fa-gauge-high';
    }

    public function guide(): ?string
    {
        // Paling banyak 255 huruf (batas kamus terjemahan).
        return 'The dashboards of the opening screen. Overall is arranged by its owner; the others are templates per area. '
            .'Set the title, icon, the module a user must be allowed to open, the order and whether it is active.';
    }

    protected function query(): Builder
    {
        return SysDashboard::query()->orderBy('sort_order')->orderBy('title');
    }

    public function columns(): array
    {
        return [
            ['key' => 'dashboard_code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '150px'],
            ['key' => 'title', 'label' => 'Title', 'required' => true, 'width' => '220px'],
            ['key' => 'icon', 'label' => 'Icon', 'width' => '240px'],
            ['key' => 'module_code', 'label' => 'Module', 'type' => 'select', 'options' => SecModule::query()->orderBy('sort_order')->pluck('module_name', 'module_code')->all(), 'width' => '190px'],
            ['key' => 'min_level', 'label' => 'Minimum level', 'type' => 'number', 'width' => '110px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'width' => '80px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function prepare(array $in): array
    {
        if (isset($in['dashboard_code']) && is_string($in['dashboard_code'])) {
            $in['dashboard_code'] = strtoupper(trim($in['dashboard_code']));
        }
        $in['min_level'] = ($in['min_level'] ?? '') === '' || $in['min_level'] === null ? 0 : $in['min_level'];
        $in['sort_order'] = ($in['sort_order'] ?? '') === '' || $in['sort_order'] === null ? 0 : $in['sort_order'];

        return $in;
    }

    protected function rules(): array
    {
        return ['dashboard_code' => ['string', 'max:30', 'regex:/^[A-Z0-9_]+$/'], 'title' => ['string', 'max:60'], 'icon' => ['nullable', 'string', 'max:60'],
            'min_level' => ['integer', 'min:0', 'max:9'], 'sort_order' => ['integer', 'min:0'], 'module_code' => ['nullable', Rule::exists(SecModule::class, 'module_code')]];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $code = $id ? SysDashboard::query()->whereKey($id)->value('dashboard_code') : ($in['dashboard_code'] ?? null);
        $active = filter_var($in['activate'] ?? true, FILTER_VALIDATE_BOOLEAN);

        return $code === SysDashboard::OVERALL && ! $active ? 'The Overall dashboard cannot be switched off.' : null;
    }
}
