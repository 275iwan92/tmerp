<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysBoardTemplate;
use App\Models\Sys\SysWidget;
use App\Support\V2\Grid\DbLookup;
use Devlib\Components\WidgetBoard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Setup > Opening Board Templates: susunan bawaan papan per profil. Dipakai saat papan user pertama kali dibuat dan saat
// user memilih Reset to Template; papan yang sudah diatur user tidak ikut berubah.
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
        return 'The board a user starts with, per profile: which widgets, in which order and how wide. '
            .'It is copied when the board is first opened and on Reset to Template; boards already arranged by their owner do not change.';
    }

    protected function query(): Builder
    {
        return SysBoardTemplate::query()->orderBy('profile_code')->orderBy('sort_order');
    }

    public function columns(): array
    {
        return [
            ['key' => 'profile_code', 'label' => 'Profile', 'type' => 'select', 'options' => SysBoardTemplate::PROFILES, 'required' => true, 'width' => '360px'],
            ['key' => 'widget_key', 'label' => 'Widget', 'type' => 'select', 'options' => SysWidget::query()->orderBy('title')->pluck('title', 'widget_key')->all(), 'required' => true, 'width' => '280px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'required' => true, 'width' => '90px'],
            ['key' => 'size', 'label' => 'Width (of 12)', 'type' => 'select', 'options' => array_combine(WidgetBoard::SIZES, WidgetBoard::SIZES), 'required' => true, 'width' => '120px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['profile_code' => [Rule::in(array_keys(SysBoardTemplate::PROFILES))], 'widget_key' => [Rule::exists(SysWidget::class, 'widget_key')],
            'sort_order' => ['integer', 'min:0'], 'size' => [Rule::in(WidgetBoard::SIZES)]];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $twice = SysBoardTemplate::query()->where('profile_code', $in['profile_code'] ?? null)->where('widget_key', $in['widget_key'] ?? null)
            ->when($id, fn (Builder $q) => $q->whereKeyNot($id))->exists();

        return $twice ? 'This widget is already in the template of that profile.' : null;
    }
}
