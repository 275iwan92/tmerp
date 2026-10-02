<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Laporan: Karyawan Tanpa No. WhatsApp (v1: pages::hrm.employee-contact-gap).
class ContactGapGrid extends HrmGrid
{
    public function slug(): string
    {
        return 'hrm-contact-gap';
    }

    public function title(): string
    {
        return t('Employees Without WhatsApp No.');
    }

    public function group(): string
    {
        return 'Employee › Report';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    protected function models(): array
    {
        return [EmpEmployee::class, OrgPosition::class, OrgDepartment::class];
    }

    public function search(): string
    {
        return 'Search name, code...';
    }

    protected function query(): Builder
    {
        return EmpEmployee::query()->with('position.department')->where('activate', true)
            ->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))->orderBy('full_name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Employee', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'position', 'label' => 'Position'],
            ['key' => 'birth', 'label' => 'Birth Date', 'type' => 'date'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->full_name, 'code' => $m->employee_code, 'dept' => $m->position?->department?->department_name,
            'position' => $m->position?->position_name, 'birth' => $m->birth_date?->toDateString(),
        ];
    }

    public function filters(): array
    {
        return ['dept' => 'Department', 'position' => 'Position'];
    }
}
