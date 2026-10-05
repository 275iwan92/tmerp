<?php

namespace App\Support\V2\Grid\Maint;

use App\Services\Maintenance\MaintenanceService;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DocListGrid;

// Daftar Preventive Plan (Grid Transactional) di layar v2 maintenance-plan: Open / + New Record = form di layar yang sama.
class MaintenancePlanListGrid extends DocListGrid
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
        return ['tm2prd.mnt_plan', 'tm2prd.mnt_plan_task', 'tm2mst.prod_machine'];
    }

    public function search(): string
    {
        return 'Search plan, machine...';
    }

    public function newUrl(): ?string
    {
        return MaintenanceService::canDecide(auth()->user()) ? parent::newUrl() : null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Plan Code / Machine', 'type' => 'doc', 'party' => 'machine'],
            ['key' => 'name', 'label' => 'Plan Name'],
            ['key' => 'type', 'label' => 'WO Type', 'type' => 'badge', 'tones' => Maintenance::TYPE_TONES],
            ['key' => 'trigger', 'label' => 'Trigger'],
            ['key' => 'mode', 'label' => 'Schedule'],
            ['key' => 'last', 'label' => 'Last Done', 'type' => 'date'],
            ['key' => 'next', 'label' => 'Next Due', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'tones' => ['Overdue' => 'danger', 'Due' => 'warn', 'OK' => 'success']],
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
        return [['label' => 'All'], ['label' => 'Overdue', 'where' => ['due' => 'Overdue'], 'tone' => 'danger'], ['label' => 'Due soon', 'where' => ['due' => 'Due'], 'tone' => 'warn'], ['label' => 'Inspection', 'where' => ['type' => 'Inspection'], 'tone' => 'success']];
    }

    public function filters(): array
    {
        return ['machine' => 'Machine', 'type' => 'WO Type', 'due' => 'Due'];
    }
}
