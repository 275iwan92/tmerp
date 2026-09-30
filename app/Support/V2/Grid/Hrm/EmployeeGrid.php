<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Master Employee (v1: pages::master.employee).
class EmployeeGrid extends HrmGrid
{
    private array $attendance = [];

    public function slug(): string
    {
        return 'hrm-employee';
    }

    public function title(): string
    {
        return 'Master Employee';
    }

    public function family(): string
    {
        return self::MASTER;
    }

    protected function v1Route(): ?string
    {
        return 'hrm.employee';
    }

    protected function v1Component(): ?string
    {
        return 'pages::master.employee';
    }

    protected function models(): array
    {
        return [EmpEmployee::class, OrgPosition::class, OrgDepartment::class, AttDailyStatus::class];
    }

    public function search(): string
    {
        return 'Cari nama, kode, NIK, jabatan...';
    }

    protected function query(): Builder
    {
        return EmpEmployee::query()->with('position.department')->orderBy('full_name');
    }

    protected function prefetch(Collection $items): void
    {
        $this->attendance = AttDailyStatus::query()->whereYear('attendance_date', now()->year)
            ->whereIn('employee_id', $items->modelKeys())->groupBy('employee_id')
            ->selectRaw('employee_id, COUNT(*) n')->pluck('n', 'employee_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => 'Foto', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Employee', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'position', 'label' => 'Position'],
            ['key' => 'dept', 'label' => 'Departemen'],
            ['key' => 'work_status', 'label' => 'Status Kerja', 'type' => 'badge', 'tones' => ['Bekerja' => 'success', 'Dirumahkan' => 'warn', 'Cuti' => 'info']],
            ['key' => 'pay', 'label' => 'Salary', 'type' => 'badge', 'tones' => ['Mingguan' => 'warn', 'Bulanan' => 'info']],
            ['key' => 'join_date', 'label' => 'Join Date', 'type' => 'date'],
            ['key' => 'attendance_ytd', 'label' => 'Attendance this year (days)', 'type' => 'link', 'format' => 'number'],
            ['key' => 'nik', 'label' => 'NIK KTP', 'visible' => false],
            ['key' => 'phone', 'label' => 'No. HP', 'visible' => false],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'photo' => $m->photo_path ? asset('storage/'.$m->photo_path) : null,
            'name' => $m->full_name,
            'code' => $m->employee_code,
            'position' => $m->position?->position_name,
            'dept' => $m->position?->department?->department_name,
            'work_status' => ['WORKING' => 'Bekerja', 'DIRUMAHKAN' => 'Dirumahkan', 'CUTI' => 'Cuti'][$m->work_status] ?? $m->work_status,
            'pay' => ['WEEKLY' => 'Mingguan', 'MONTHLY' => 'Bulanan'][$m->payment_frequency] ?? $m->payment_frequency,
            'join_date' => $m->join_date?->toDateString(),
            'attendance_ytd' => (int) ($this->attendance[$m->getKey()] ?? 0),
            'nik' => $m->nik_ktp,
            'phone' => $m->phone,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Active', 'where' => ['active' => true], 'tone' => 'success'],
            ['label' => 'Mingguan', 'where' => ['active' => true, 'pay' => 'Mingguan'], 'tone' => 'warn'],
            ['label' => 'Bulanan', 'where' => ['active' => true, 'pay' => 'Bulanan'], 'tone' => 'info'],
            ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['dept' => 'Departemen', 'position' => 'Jabatan', 'work_status' => 'Status Kerja', 'pay' => 'Gaji'];
    }
}
