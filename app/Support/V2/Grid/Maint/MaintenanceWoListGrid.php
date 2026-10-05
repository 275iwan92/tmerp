<?php

namespace App\Support\V2\Grid\Maint;

use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DocListGrid;

// Daftar Maintenance Work Order (Grid Transactional) di layar v2 maintenance-wo: Open / + New Record = form di layar yang sama.
class MaintenanceWoListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'maintenance-wo';
    }

    public function title(): string
    {
        return 'Maintenance Work Order';
    }

    public function module(): string
    {
        return 'MAINT';
    }

    protected function listId(): string
    {
        return 'mnt-wo-list';
    }

    protected function listRows(): array
    {
        return Maintenance::woRows();
    }

    protected function sources(): array
    {
        return ['tm2prd.mnt_wo', 'tm2mst.prod_machine', 'tm2mst.prod_machine_downtime'];
    }

    public function search(): string
    {
        return 'Search WO, machine, technician...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'WO No. / Machine', 'type' => 'doc', 'party' => 'machine'],
            ['key' => 'desc', 'label' => 'Description'],
            ['key' => 'support', 'label' => 'Machine Kind', 'visible' => false],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => Maintenance::TYPE_TONES],
            ['key' => 'priority', 'label' => 'Priority', 'type' => 'badge', 'tones' => Maintenance::PRIO_TONES],
            ['key' => 'work', 'label' => 'Work Status', 'type' => 'badge', 'tones' => Maintenance::WORK_TONES],
            ['key' => 'planned_at', 'label' => 'Planned'],
            ['key' => 'technician', 'label' => 'Technician'],
            ['key' => 'downtime', 'label' => 'Downtime'],
            ['key' => 'source', 'label' => 'Source', 'visible' => false],
            ['key' => 'cost', 'label' => 'Cost', 'type' => 'money', 'sum' => true, 'visible' => false],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Spare Parts', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'plan', 'label' => 'Planned Qty', 'type' => 'number'],
            ['key' => 'used', 'label' => 'Used Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
        ]];
    }

    public function stats(): array
    {
        return Maintenance::woStats();
    }

    public function filters(): array
    {
        return ['type' => 'Type', 'work' => 'Work Status', 'machine' => 'Machine', 'technician' => 'Technician', 'priority' => 'Priority'];
    }
}
