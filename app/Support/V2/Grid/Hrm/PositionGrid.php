<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgLevel;
use App\Models\Master\OrgPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Master Posisi (v1: pages::hrm.position).
class PositionGrid extends HrmGrid
{
    private array $holders = [];

    public function slug(): string
    {
        return 'hrm-position';
    }

    public function title(): string
    {
        return t('Position Master');
    }

    public function family(): string
    {
        return self::MASTER;
    }

    public function group(): string
    {
        return 'Organization';
    }

    protected function v1Route(): ?string
    {
        return 'hrm.position';
    }

    protected function v1Component(): ?string
    {
        return 'pages::hrm.position';
    }

    protected function models(): array
    {
        return [OrgPosition::class, OrgDepartment::class, OrgLevel::class, EmpEmployee::class];
    }

    public function search(): string
    {
        return 'Search position, code, department...';
    }

    protected function query(): Builder
    {
        return OrgPosition::query()->with(['department', 'level', 'reportsTo'])->orderBy('position_name');
    }

    protected function prefetch(Collection $items): void
    {
        $this->holders = EmpEmployee::query()->where('activate', true)->whereIn('position_id', $items->modelKeys())
            ->groupBy('position_id')->selectRaw('position_id, COUNT(*) n')->pluck('n', 'position_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Position', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'level', 'label' => 'Level'],
            ['key' => 'reports_to', 'label' => 'Reports To'],
            ['key' => 'holders', 'label' => 'Active employees', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->position_name, 'code' => $m->position_code, 'dept' => $m->department?->department_name,
            'level' => $m->level?->level_name, 'reports_to' => $m->reportsTo?->position_name,
            'holders' => (int) ($this->holders[$m->getKey()] ?? 0),
        ];
    }

    public function filters(): array
    {
        return ['dept' => 'Department', 'level' => 'Level'];
    }
}
