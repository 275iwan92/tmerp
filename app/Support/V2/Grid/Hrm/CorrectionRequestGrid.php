<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpCorrectionRequest;
use App\Models\Master\EmpEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Koreksi Data Karyawan (v1: pages::hrm.correction-requests).
class CorrectionRequestGrid extends HrmGrid
{
    public function slug(): string
    {
        return 'hrm-correction';
    }

    public function title(): string
    {
        return 'Koreksi Data Karyawan';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    protected function v1Route(): ?string
    {
        return 'hrm.correction-requests';
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function models(): array
    {
        return [EmpCorrectionRequest::class, EmpEmployee::class];
    }

    public function search(): string
    {
        return 'Cari karyawan, field, nilai...';
    }

    protected function query(): Builder
    {
        return EmpCorrectionRequest::query()->with('employee')
            ->orderByRaw("FIELD(status, 'PENDING', 'APPROVED', 'REJECTED')")->orderByDesc('requested_at');
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Employee No. / Name', 'type' => 'doc', 'party' => 'name'],
            ['key' => 'field', 'label' => 'Field'],
            ['key' => 'old', 'label' => 'Nilai Lama'],
            ['key' => 'new', 'label' => 'New Value'],
            ['key' => 'reason', 'label' => 'Alasan'],
            ['key' => 'requested', 'label' => 'Diajukan', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::REQUEST_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->employee?->full_name, 'code' => $m->employee?->employee_code, 'field' => $m->field_name,
            'old' => $m->old_value, 'new' => $m->newValueLabel(), 'reason' => $m->reason,
            'requested' => $m->requested_at?->toDateString(), 'status' => $m->status,
        ];
    }

    public function stats(): array
    {
        return $this->requestStats();
    }

    public function filters(): array
    {
        return ['field' => 'Field', 'status' => 'Status'];
    }
}
