<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysBoardTemplate;
use App\Models\Sys\SysDashboard;
use App\Models\Sys\SysWidget;
use App\Support\V2\Grid\DbLookup;
use Devlib\Components\WidgetBoard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Setup > Opening Board Templates: isi tiap dashboard. Dashboard bidang (Sales, Purchasing, ...) menampilkan barisnya apa adanya;
// baris OVERALL = papan awal user dan tujuan Reset to Template (papan yang sudah diatur pemiliknya tidak ikut berubah).
class BoardTemplateLookup extends DbLookup
{
    public function slug(): string
    {
        return 'setup-board-template';
    }

    public function title(): string
    {
        return 'Opening Board Templates';
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
        return 'fa-solid fa-table-columns';
    }

    public function guide(): ?string
    {
        return 'Widgets of each dashboard: which, in which order and how wide. An area dashboard shows its rows as they are; '
            .'the Overall rows are the board a user starts with and returns to on Reset to Template.';
    }

    protected function query(): Builder
    {
        return SysBoardTemplate::query()->orderBy('profile_code')->orderBy('sort_order');
    }

    public function columns(): array
    {
        return [
            ['key' => 'profile_code', 'label' => 'Dashboard', 'type' => 'select', 'options' => SysDashboard::query()->orderBy('sort_order')->pluck('title', 'dashboard_code')->all(),
                'required' => true, 'width' => '220px'],
            ['key' => 'widget_key', 'label' => 'Widget', 'type' => 'select', 'options' => SysWidget::query()->orderBy('title')->pluck('title', 'widget_key')->all(), 'required' => true, 'width' => '280px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'required' => true, 'width' => '90px'],
            ['key' => 'size', 'label' => 'Width (of 12)', 'type' => 'select', 'options' => array_combine(WidgetBoard::SIZES, WidgetBoard::SIZES), 'required' => true, 'width' => '120px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['profile_code' => [Rule::exists(SysDashboard::class, 'dashboard_code')], 'widget_key' => [Rule::exists(SysWidget::class, 'widget_key')],
            'sort_order' => ['integer', 'min:0'], 'size' => [Rule::in(WidgetBoard::SIZES)]];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $twice = SysBoardTemplate::query()->where('profile_code', $in['profile_code'] ?? null)->where('widget_key', $in['widget_key'] ?? null)
            ->when($id, fn (Builder $q) => $q->whereKeyNot($id))->exists();

        return $twice ? t('This widget is already in the template of that dashboard.') : null;
    }
}
