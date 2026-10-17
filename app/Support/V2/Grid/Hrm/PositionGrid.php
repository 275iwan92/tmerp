<?php

/*
 * REMARK
 * Tujuan       : Grid Master Position: daftar jabatan, departemen, level, atasan, jumlah pemegang (utama + tambahan); Open / + New Record membuka layar form v2 hrm-position-form.
 * Sumber hulu  : tm2mst.org_position, org_department, org_level, emp_employee, emp_employee_position (via PositionDesk::holderCounts).
 * Input        : Query string saringan grid.
 * Output       : x-dl::grid lewat DbGrid; alamat buka = v2:hrm-position-form?id={id}, New = ?new=1.
 * Dipakai oleh : Menu HRM > Organization > Master Position (slug hrm-position).
 * Status       : Aktif. Layar v1 pages::hrm.position dipensiunkan (2026-10-17); rute lama /hrm/position mengalihkan ke grid ini.
 */

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpEmployeePosition;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgLevel;
use App\Models\Master\OrgPosition;
use App\Support\V2\Data\PositionDesk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Master Position: grid + form v2 (hrm-position-form).
class PositionGrid extends HrmGrid
{
    private array $holders = [];

    public function slug(): string
    {
        return 'hrm-position';
    }

    public function title(): string
    {
        return t('Master Position');
    }

    public function family(): string
    {
        return self::MASTER;
    }

    public function group(): string
    {
        return 'Organization';
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'hrm-position-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'hrm-position-form').'?new=1';
    }

    protected function canCreate(): bool
    {
        return true;
    }

    protected function models(): array
    {
        return [OrgPosition::class, OrgDepartment::class, OrgLevel::class, EmpEmployee::class, EmpEmployeePosition::class];
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
        $this->holders = PositionDesk::holderCounts($items->modelKeys());
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
