<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Presensi harian (v1: pages::hrm.attendance-daily). 60 hari terakhir; buka baris = override status di layar v1.
class AttendanceGrid extends HrmGrid
{
    public const LATE = ['T-5', 'T+5', 'T+15', 'PAT-5', 'PAT+5'];

    public const ABSENT = ['A', 'I', 'IS', 'S', 'C', 'CL'];

    public function slug(): string
    {
        return 'hrm-attendance';
    }

    public function title(): string
    {
        return 'Presensi';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Payroll';
    }

    protected function v1Route(): ?string
    {
        return 'hrm.attendance-daily';
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function models(): array
    {
        return [AttDailyStatus::class, EmpEmployee::class];
    }

    public function search(): string
    {
        return 'Cari karyawan, status...';
    }

    protected function query(): Builder
    {
        return AttDailyStatus::query()->with('employee')->where('attendance_date', '>=', now()->subDays(60)->toDateString())
            ->orderByDesc('attendance_date')->orderBy('employee_id');
    }

    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'name', 'label' => 'Employee', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'shift', 'label' => 'Shift'],
            ['key' => 'in', 'label' => 'Sign in'],
            ['key' => 'out', 'label' => 'Pulang'],
            ['key' => 'late', 'label' => 'Telat (Mnt)', 'type' => 'number'],
            ['key' => 'overtime', 'label' => 'Overtime (Min)', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['H' => 'success', 'A' => 'danger'] + array_fill_keys(self::LATE, 'warn')],
            ['key' => 'source', 'label' => 'Sumber'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'date' => $m->attendance_date?->toDateString(), 'name' => $m->employee?->full_name, 'code' => $m->employee?->employee_code,
            'shift' => $m->shift_start ? $m->shift_start->format('H:i').'–'.$m->shift_end?->format('H:i') : null,
            'in' => $m->finger_in?->format('H:i'), 'out' => $m->finger_out?->format('H:i'),
            'late' => (int) $m->minutes_late, 'overtime' => (int) $m->overtime_minutes, 'status' => $m->status_code, 'source' => $m->source,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'Last 60 days'],
            ['label' => 'Hadir', 'where' => ['status' => 'H'], 'tone' => 'success'],
            ['label' => 'Terlambat', 'where' => ['status' => self::LATE], 'tone' => 'warn'],
            ['label' => 'Absent', 'where' => ['status' => self::ABSENT], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['status' => 'Status', 'source' => 'Sumber'];
    }
}
