<?php

namespace App\Support\V2\Grid\Hrm;

use App\Support\V2\Data\Overtime;
use App\Support\V2\Grid\DocListGrid;

// Daftar Overtime (Grid Transactional) di layar v2 hrm-overtime: Open / + New Record = form di layar yang sama.
class OvertimeListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'hrm-overtime';
    }

    public function title(): string
    {
        return 'Overtime';
    }

    public function module(): string
    {
        return 'HRM';
    }

    public function moduleLabel(): string
    {
        return 'Human Resources';
    }

    public function group(): string
    {
        return 'Time Attendance';
    }

    public function icon(): string
    {
        return 'fa-solid fa-business-time';
    }

    protected function listRows(): array
    {
        return Overtime::rows();
    }

    protected function sources(): array
    {
        return ['tm2mst.emp_overtime_request', 'tm2mst.emp_employee'];
    }

    public function search(): string
    {
        return 'Search employee, code, type...';
    }

    public function guide(): ?string
    {
        return 'Overtime requests per employee per date. Only APPROVED requests are counted when the attendance of that day is calculated. Draft -> Submit -> Approve / Reject.';
    }

    public function columns(): array
    {
        return [
            ['key' => 'employee', 'label' => 'Employee', 'type' => 'doc', 'party' => 'code'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'type', 'label' => 'Overtime Type'],
            ['key' => 'minutes', 'label' => 'Minutes', 'type' => 'number'],
            ['key' => 'hours', 'label' => 'Hours', 'type' => 'number', 'decimals' => 2],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => Overtime::STATUS_TONES],
            ['key' => 'by', 'label' => 'Decided By'],
            ['key' => 'at', 'label' => 'Decided On', 'type' => 'date'],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'], ['label' => 'Submitted', 'where' => ['status' => 'SUBMITTED'], 'tone' => 'warn', 'default' => true],
            ['label' => 'Approved', 'where' => ['status' => 'APPROVED'], 'tone' => 'success'], ['label' => 'Rejected', 'where' => ['status' => 'REJECTED'], 'tone' => 'danger']];
    }

    public function filters(): array
    {
        return ['employee' => 'Employee', 'type' => 'Overtime Type'];
    }
}
