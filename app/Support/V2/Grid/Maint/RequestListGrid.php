<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Mnt\MntRequest;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DocListGrid;

// Daftar Maintenance Request (Grid Transactional). + New Record = form lapor masalah; Open = request dengan Accept / Reject untuk kepala maintenance.
class RequestListGrid extends DocListGrid
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
        return 'mnt-request-list';
    }

    protected function listRows(): array
    {
        return Maintenance::requestRows();
    }

    protected function sources(): array
    {
        return ['tm2mnt.mnt_request', 'tm2mnt.mnt_asset'];
    }

    public function search(): string
    {
        return 'Search request, asset, symptom...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Request / Asset', 'type' => 'doc', 'party' => 'asset'],
            ['key' => 'symptom', 'label' => 'Symptom'],
            ['key' => 'kind', 'label' => 'Kind', 'type' => 'badge', 'tones' => MntRequest::KIND_TONES],
            ['key' => 'priority', 'label' => 'Priority'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => MntRequest::STATUS_TONES],
            ['key' => 'date', 'label' => 'Reported', 'type' => 'date'],
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'order', 'label' => 'Order'],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Open', 'where' => ['status' => 'Open'], 'tone' => 'warn'], ['label' => 'Accepted', 'where' => ['status' => 'Accepted'], 'tone' => 'success'],
            ['label' => 'Rejected', 'where' => ['status' => 'Rejected'], 'tone' => 'danger']];
    }

    public function filters(): array
    {
        return ['asset' => 'Asset', 'kind' => 'Kind', 'priority' => 'Priority', 'status' => 'Status'];
    }
}
