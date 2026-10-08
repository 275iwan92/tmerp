<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Mnt\MntOrder;
use App\Services\Maintenance\MaintenanceAccess;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DocListGrid;

// Daftar Maintenance Order (Grid Transactional). Satu desain untuk semua pintu menu; turunan hanya menyatakan seri nomor:
// PMO (Preventive, Inspection), BMO (Breakdown, Corrective). Open / + New Record = form di layar yang sama.
abstract class OrderListGrid extends DocListGrid
{
    /** Seri nomor dokumen: PMO atau BMO. */
    abstract protected function series(): string;

    protected function listRows(): array
    {
        return Maintenance::orderRows($this->series());
    }

    protected function sources(): array
    {
        return ['tm2mnt.mnt_order', 'tm2mnt.mnt_order_task', 'tm2mnt.mnt_asset'];
    }

    public function module(): string
    {
        return 'MAINT';
    }

    public function search(): string
    {
        return 'Search order, asset, technician...';
    }

    public function newUrl(): ?string
    {
        return MaintenanceAccess::canManage(auth()->user()) ? parent::newUrl() : null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Order / Asset', 'type' => 'doc', 'party' => 'asset'],
            ['key' => 'desc', 'label' => 'Description'],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => MntOrder::TYPE_TONES],
            ['key' => 'priority', 'label' => 'Priority'],
            ['key' => 'work', 'label' => 'Status', 'type' => 'badge', 'tones' => MntOrder::WORK_TONES],
            ['key' => 'planned', 'label' => 'Planned', 'type' => 'date'],
            ['key' => 'technician', 'label' => 'Technician'],
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'cost', 'label' => 'Cost (IDR)', 'type' => 'money'],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Checklist', 'columns' => [
            ['key' => 'seq', 'label' => 'No.', 'type' => 'number'],
            ['key' => 'task', 'label' => 'Task', 'strong' => true],
            ['key' => 'kind', 'label' => 'Kind'],
            ['key' => 'result', 'label' => 'Result'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All Open', 'where' => ['open' => 'Open']], ['label' => 'In Progress', 'where' => ['work' => 'In Progress'], 'tone' => 'info'],
            ['label' => 'Waiting Part', 'where' => ['work' => 'Waiting Part'], 'tone' => 'warn'], ['label' => 'Overdue', 'where' => ['overdue' => 'Overdue'], 'tone' => 'danger'],
            ['label' => 'Done', 'where' => ['work' => 'Done'], 'tone' => 'success']];
    }

    public function filters(): array
    {
        return ['asset' => 'Asset', 'type' => 'Type', 'work' => 'Status', 'technician' => 'Technician'];
    }
}
