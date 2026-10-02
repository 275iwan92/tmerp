<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgDepartment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Laporan: Kedisiplinan Harian (v1: pages::hrm.discipline-report) — terlambat & tidak masuk, 31 hari terakhir.
class DisciplineGrid extends HrmGrid
{
    private const LABELS = [
        'T-5' => 'Late < 5 min', 'T+5' => 'Late 6-15 min', 'T+15' => 'Late > 15 min', 'PAT-5' => 'Early leave < 5 min', 'PAT+5' => 'Early leave > 5 min',
        'A' => 'Absent without notice', 'S' => 'Sick', 'I' => 'Permission', 'IS' => 'Approved permission', 'C' => 'Leave', 'CL' => 'Maternity leave',
    ];

    public function slug(): string
    {
        return 'hrm-discipline';
    }

    public function title(): string
    {
        return t('Daily Discipline');
    }

    public function group(): string
    {
        return 'Time Attendance › Report';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    protected function models(): array
    {
        return [AttDailyStatus::class, EmpEmployee::class, OrgDepartment::class];
    }

    public function search(): string
    {
        return 'Search employee, category...';
    }

    protected function query(): Builder
    {
        return AttDailyStatus::query()->with('employee.position.department')
            ->where('attendance_date', '>=', now()->subDays(31)->toDateString())
            ->whereIn('status_code', array_merge(AttendanceGrid::LATE, AttendanceGrid::ABSENT))
            ->orderByDesc('attendance_date')->orderByDesc('minutes_late');
    }

    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'name', 'label' => 'Employee', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'shift', 'label' => 'Shift Hours'],
            ['key' => 'in', 'label' => 'Clock-in Time'],
            ['key' => 'late', 'label' => 'Late (min)', 'type' => 'number'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'kind', 'label' => 'Type'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'date' => $m->attendance_date?->toDateString(), 'name' => $m->employee?->full_name, 'code' => $m->employee?->employee_code,
            'dept' => $m->employee?->position?->department?->department_name, 'shift' => $m->shift_start?->format('H:i'),
            'in' => $m->finger_in?->format('H:i'), 'late' => (int) $m->minutes_late,
            'category' => isset(self::LABELS[$m->status_code]) ? t(self::LABELS[$m->status_code]) : $m->status_code,
            'kind' => t(in_array($m->status_code, AttendanceGrid::LATE, true) ? 'Late' : 'Absent'),
        ];
    }

    public function filters(): array
    {
        return ['kind' => 'Type', 'category' => 'Category', 'dept' => 'Department'];
    }
}
