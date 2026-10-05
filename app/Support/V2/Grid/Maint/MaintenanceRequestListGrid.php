<?php

namespace App\Support\V2\Grid\Maint;

use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DocListGrid;

// Daftar Maintenance Request (Grid Transactional) di layar v2 maintenance-request: Open / + New Record = form di layar yang sama.
class MaintenanceRequestListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'maintenance-request';
    }

    public function title(): string
    {
        return 'Maintenance Request';
    }

    public function module(): string
    {
        return 'MAINT';
    }

    protected function listId(): string
    {
        return 'mnt-req-list';
    }

    protected function listRows(): array
    {
        return Maintenance::requestRows();
    }

    protected function sources(): array
    {
        return ['tm2prd.mnt_request', 'tm2mst.prod_machine'];
    }

    public function search(): string
    {
        return 'Search request, machine, symptom...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Request No. / Machine', 'type' => 'doc', 'party' => 'machine'],
            ['key' => 'symptom', 'label' => 'Symptom'],
            ['key' => 'at', 'label' => 'Reported'],
            ['key' => 'kind', 'label' => 'Kind', 'type' => 'badge', 'tones' => ['Breakdown' => 'danger', 'Corrective' => 'warn', 'Safety' => 'danger', 'Improvement' => 'info']],
            ['key' => 'priority', 'label' => 'Priority', 'type' => 'badge', 'tones' => Maintenance::PRIO_TONES],
            ['key' => 'stopped', 'label' => 'Machine', 'type' => 'badge', 'tones' => ['Stopped' => 'danger', 'Running' => 'success']],
            ['key' => 'reporter', 'label' => 'Reported by', 'visible' => false],
            ['key' => 'age', 'label' => 'Waiting', 'visible' => false],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => Maintenance::REQ_TONES],
            ['key' => 'wo', 'label' => 'Work Order'],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Open', 'where' => ['status' => 'Open'], 'tone' => 'warn'], ['label' => 'Machine stopped', 'where' => ['stopped' => 'Stopped', 'status' => 'Open'], 'tone' => 'danger'], ['label' => 'Accepted', 'where' => ['status' => 'Accepted'], 'tone' => 'success'], ['label' => 'Rejected', 'where' => ['status' => 'Rejected'], 'tone' => 'neutral']];
    }

    public function filters(): array
    {
        return ['machine' => 'Machine', 'kind' => 'Kind', 'priority' => 'Priority', 'status' => 'Status'];
    }
}
