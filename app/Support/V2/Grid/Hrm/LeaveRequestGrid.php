<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpLeaveRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Approval Cuti/Sakit/Izin (v1: pages::hrm.leave-requests). Setujui/Tolak di layar tujuan, bukan di baris grid.
class LeaveRequestGrid extends HrmGrid
{
    public function slug(): string
    {
        return 'hrm-leave';
    }

    public function title(): string
    {
        return 'Approval Cuti/Sakit/Izin';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    protected function v1Route(): ?string
    {
        return 'hrm.leave-requests';
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function models(): array
    {
        return [EmpLeaveRequest::class, EmpEmployee::class];
    }

    public function search(): string
    {
        return 'Cari karyawan, jenis, alasan...';
    }

    protected function query(): Builder
    {
        return EmpLeaveRequest::query()->with('employee')
            ->orderByRaw("FIELD(status, 'PENDING', 'APPROVED', 'REJECTED')")->orderByDesc('requested_at');
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Employee', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'from', 'label' => 'From', 'type' => 'date'],
            ['key' => 'to', 'label' => 'Until', 'type' => 'date'],
            ['key' => 'days', 'label' => 'Day', 'type' => 'number'],
            ['key' => 'reason', 'label' => 'Alasan'],
            ['key' => 'requested', 'label' => 'Diajukan', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::REQUEST_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->employee?->full_name, 'code' => $m->employee?->employee_code, 'type' => $m->leave_type,
            'from' => $m->date_from?->toDateString(), 'to' => $m->date_to?->toDateString(), 'days' => (int) $m->total_days,
            'reason' => $m->reason, 'requested' => $m->requested_at ? substr((string) $m->requested_at, 0, 10) : null, 'status' => $m->status,
        ];
    }

    public function stats(): array
    {
        return $this->requestStats();
    }

    public function filters(): array
    {
        return ['type' => 'Jenis', 'status' => 'Status'];
    }
}
