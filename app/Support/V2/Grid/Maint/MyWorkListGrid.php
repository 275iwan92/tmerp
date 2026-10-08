<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Mnt\MntOrder;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DocListGrid;

// My Work -- daftar kerja teknisi yang sedang login: order Confirmed yang belum Close, ditugaskan kepadanya, ditambah yang belum punya teknisi (bisa diambil
// kepala maintenance). Open membuka order di layar PMO / BMO yang sesuai. Tidak ada + New Record: order lahir dari Plan atau Request.
class MyWorkListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'maintenance-my-work';
    }

    public function title(): string
    {
        return 'My Work';
    }

    public function module(): string
    {
        return 'MAINT';
    }

    protected function listId(): string
    {
        return 'mnt-my-work-list';
    }

    protected function listRows(): array
    {
        return Maintenance::myWorkRows();
    }

    protected function sources(): array
    {
        return ['tm2mnt.mnt_order', 'tm2mnt.mnt_asset'];
    }

    public function openUrl(): ?string
    {
        return url('v2/maintenance/order').'/{id}';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function search(): string
    {
        return 'Search order, asset...';
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
            ['key' => 'owner', 'label' => 'Assigned'],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Mine', 'where' => ['owner' => 'Mine'], 'tone' => 'info'], ['label' => 'Unassigned', 'where' => ['owner' => 'Unassigned'], 'tone' => 'warn'],
            ['label' => 'In Progress', 'where' => ['work' => 'In Progress'], 'tone' => 'accent'], ['label' => 'Waiting Part', 'where' => ['work' => 'Waiting Part'], 'tone' => 'warn']];
    }

    public function filters(): array
    {
        return ['asset' => 'Asset', 'type' => 'Type', 'priority' => 'Priority', 'work' => 'Status'];
    }
}
