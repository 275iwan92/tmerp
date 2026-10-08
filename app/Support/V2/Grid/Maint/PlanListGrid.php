<?php

namespace App\Support\V2\Grid\Maint;

use App\Services\Maintenance\MaintenanceAccess;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DocListGrid;

// Daftar Preventive Plan (Grid Transactional) di layar v2 maintenance-plan: Open / + New Record = form di layar yang sama.
class PlanListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'maintenance-plan';
    }

    public function title(): string
    {
        return 'Preventive Plan';
    }

    public function module(): string
    {
        return 'MAINT';
    }

    protected function listId(): string
    {
        return 'mnt-plan-list';
    }

    protected function listRows(): array
    {
        return Maintenance::planRows();
    }

    protected function sources(): array
    {
        return ['tm2mnt.mnt_plan', 'tm2mnt.mnt_plan_task', 'tm2mnt.mnt_asset'];
    }

    public function search(): string
    {
        return 'Search plan, asset...';
    }

    public function newUrl(): ?string
    {
        return MaintenanceAccess::canManage(auth()->user()) ? parent::newUrl() : null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Plan Code / Asset', 'type' => 'doc', 'party' => 'asset'],
            ['key' => 'name', 'label' => 'Plan Name'],
            ['key' => 'type', 'label' => 'Order Type', 'type' => 'badge', 'tones' => ['Preventive' => 'accent', 'Inspection' => 'success']],
            ['key' => 'trigger', 'label' => 'Trigger'],
            ['key' => 'mode', 'label' => 'Schedule'],
            ['key' => 'last', 'label' => 'Last Done', 'type' => 'date'],
            ['key' => 'next', 'label' => 'Next Due', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'tones' => ['Overdue' => 'danger', 'Due' => 'warn', 'OK' => 'success', 'Inactive' => 'neutral']],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Tasks', 'columns' => [
            ['key' => 'seq', 'label' => 'No.', 'type' => 'number'],
            ['key' => 'task', 'label' => 'Task', 'strong' => true],
            ['key' => 'kind', 'label' => 'Kind'],
            ['key' => 'uom', 'label' => 'Unit'],
            ['key' => 'min', 'label' => 'Min', 'type' => 'number'],
            ['key' => 'max', 'label' => 'Max', 'type' => 'number'],
            ['key' => 'required', 'label' => 'Required'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Overdue', 'where' => ['due' => 'Overdue'], 'tone' => 'danger'], ['label' => 'Due soon', 'where' => ['due' => 'Due'], 'tone' => 'warn'],
            ['label' => 'Inspection', 'where' => ['type' => 'Inspection'], 'tone' => 'success']];
    }

    public function filters(): array
    {
        return ['asset' => 'Asset', 'type' => 'Order Type', 'due' => 'Due'];
    }
}
