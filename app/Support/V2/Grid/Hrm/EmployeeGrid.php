<?php

/*
 * REMARK
 * Tujuan       : Grid Master Employee (keluarga Grid Master): daftar pegawai, stat Staff / Weekly, saringan jabatan; Open / + New Record membuka layar form v2 hrm-employee-form.
 * Sumber hulu  : tm2mst.emp_employee, org_position, org_department, att_daily_status (hitungan absen tahun berjalan).
 * Input        : Query string saringan grid.
 * Output       : x-dl::grid lewat DbGrid; alamat buka = v2:hrm-employee-form?id={id}, New = ?new=1.
 * Dipakai oleh : Menu HRM > Master Employee (slug hrm-employee).
 * Status       : Aktif. Layar v1 pages::master.employee tetap hidup untuk tab anak (Contract, Family, Education, Discipline) sampai tahap berikutnya.
 */

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

    public function openUrl(): ?string
    {
        return route('v2.screen', 'hrm-employee-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return $this->canCreate() ? route('v2.screen', 'hrm-employee-form').'?new=1' : null;
    }

    protected function models(): array
    {
        return [EmpEmployee::class, OrgPosition::class, OrgDepartment::class, AttDailyStatus::class];
    }

    public function search(): string
    {
        return 'Search name, code, NIK, position...';
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
            ['key' => 'photo', 'label' => 'Photo', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Employee', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'position', 'label' => 'Position'],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'work_status', 'label' => 'Work Status', 'type' => 'badge', 'tones' => [t('Working') => 'success', t('Furloughed') => 'warn', t('On Leave') => 'info']],
            ['key' => 'type', 'label' => 'Employee Type', 'type' => 'badge', 'tones' => [t('Staff') => 'info', t('Non-staff') => 'neutral']],
            ['key' => 'pay', 'label' => 'Payroll Method', 'type' => 'badge', 'tones' => [t('Weekly') => 'warn', t('Monthly') => 'info']],
            ['key' => 'join_date', 'label' => 'Join Date', 'type' => 'date'],
            ['key' => 'attendance_ytd', 'label' => 'Attendance this year (days)', 'type' => 'link', 'format' => 'number'],
            ['key' => 'nik', 'label' => 'NIK KTP', 'visible' => false],
            ['key' => 'phone', 'label' => 'Mobile No.', 'visible' => false],
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
            'work_status' => ['WORKING' => t('Working'), 'DIRUMAHKAN' => t('Furloughed'), 'CUTI' => t('On Leave')][$m->work_status] ?? $m->work_status,
            'type' => $m->staff_type === 'STAFF' ? t('Staff') : t('Non-staff'),
            'pay' => ['WEEKLY' => t('Weekly'), 'MONTHLY' => t('Monthly')][$m->payment_frequency] ?? $m->payment_frequency,
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
            ['label' => 'Staff', 'where' => ['active' => true, 'type' => t('Staff')], 'tone' => 'info'],
            ['label' => 'Non-staff', 'where' => ['active' => true, 'type' => t('Non-staff')], 'tone' => 'neutral'],
            ['label' => 'Weekly', 'where' => ['active' => true, 'pay' => t('Weekly')], 'tone' => 'warn'],
            ['label' => 'Monthly', 'where' => ['active' => true, 'pay' => t('Monthly')], 'tone' => 'info'],
            ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['dept' => 'Department', 'position' => 'Position', 'work_status' => 'Work Status', 'type' => 'Employee Type', 'pay' => 'Payroll Method'];
    }
}
