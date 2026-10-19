<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpLeaveRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Approval Cuti / Sakit / Izin (pengajuan dari Self-Service: PENDING menunggu HR); Open membuka form v2 hrm-leave-form tempat HR memutuskan. Tanpa New.
 * Sumber hulu  : tm2mst.emp_leave_request + emp_employee.
 * Input        : Pencarian, filter Type / Status, kotak status.
 * Output       : Baris grid (hanya baca; keputusan lewat form).
 * Dipakai oleh : Menu HRM (v2:hrm-leave), layar hrm-leave-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::hrm.leave-requests sudah dipindah ke tmerp_unused (aturan D.25, T7d HRM).
 */
class LeaveRequestGrid extends HrmGrid
{
    public function slug(): string
    {
        return 'hrm-leave';
    }

    public function title(): string
    {
        return t('Leave / Sick / Permit Approval');
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'hrm-leave-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return null;
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
        return 'Search employee, type, reason...';
    }

    protected function query(): Builder
    {
        return EmpLeaveRequest::query()->with('employee')
            ->orderByRaw("FIELD(status, 'PENDING', 'APPROVED', 'REJECTED')")->orderByDesc('requested_at');
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Employee No. / Name', 'type' => 'doc', 'party' => 'name'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'from', 'label' => 'From', 'type' => 'date'],
            ['key' => 'to', 'label' => 'Until', 'type' => 'date'],
            ['key' => 'days', 'label' => 'Day', 'type' => 'number'],
            ['key' => 'reason', 'label' => 'Reason'],
            ['key' => 'requested', 'label' => 'Submitted', 'type' => 'date'],
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
        return ['type' => 'Type', 'status' => 'Status'];
    }
}
