<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Master Shift (v1: pages::master.shift).
class ShiftGrid extends MasterDataGrid
{
    private array $employees = [];

    public function slug(): string
    {
        return 'md-shift';
    }

    public function title(): string
    {
        return 'Master Shift';
    }

    protected function v1Route(): ?string
    {
        return 'master.shift';
    }

    protected function v1Component(): ?string
    {
        return 'pages::master.shift';
    }

    protected function models(): array
    {
        return [EmpShift::class, EmpEmployee::class];
    }

    public function search(): string
    {
        return 'Search shift name, department...';
    }

    protected function query(): Builder
    {
        return EmpShift::query()->with('department')->orderBy('shift_name');
    }

    protected function prefetch(Collection $items): void
    {
        $this->employees = EmpEmployee::query()->where('activate', true)->whereIn('default_shift_id', $items->modelKeys())
            ->groupBy('default_shift_id')->selectRaw('default_shift_id, COUNT(*) n')->pluck('n', 'default_shift_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Shift Name', 'type' => 'identity', 'avatar' => false],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'employees', 'label' => 'Active employees', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['photo' => null, 'name' => $m->shift_name, 'dept' => $m->department?->department_name ?? t('All departments'),
            'employees' => (int) ($this->employees[$m->getKey()] ?? 0)];
    }

    public function filters(): array
    {
        return ['dept' => 'Department'];
    }
}
