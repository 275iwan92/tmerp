<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Inv;

use App\Support\V2\Data\CountTasks;
use App\Support\V2\Grid\DocListGrid;

// Inventory > Warehouse > Count Tasks (tm2inv.wh_task, jenis COUNT): daftar tugas hitung per posisi stok. Open = isi qty hasil hitung di layar yang sama
// (resources/views/v2/screens/wh-count-tasks.blade.php). Tugas dibuat per area (tombol Start Count atau php artisan inv:count-due).
class CountTaskGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'wh-count-tasks';
    }

    public function title(): string
    {
        return 'Count Tasks';
    }

    public function module(): string
    {
        return 'INV';
    }

    public function moduleLabel(): string
    {
        return 'Inventory';
    }

    public function group(): string
    {
        return 'Warehouse';
    }

    public function icon(): string
    {
        return 'fa-solid fa-clipboard-list';
    }

    protected function listRows(): array
    {
        return CountTasks::rows();
    }

    protected function sources(): array
    {
        return ['tm2inv.wh_task', 'tm2mst.wh_loc', 'tm2mst.wh_zone', 'tm2inv.rm_stock'];
    }

    public function search(): string
    {
        return 'Search task, bin, item...';
    }

    public function guide(): ?string
    {
        return 'One task per item in a bin of an area that is being counted. The bins are locked while counting: nothing can be moved in or out. Enter the counted quantity; a difference becomes a Stock Opname document.';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'task', 'label' => 'Task'],
            ['key' => 'zone', 'label' => 'Area'],
            ['key' => 'bin', 'label' => 'Bin'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'system', 'label' => 'System Qty', 'type' => 'number', 'decimals' => 2],
            ['key' => 'counted', 'label' => 'Counted Qty', 'type' => 'number', 'decimals' => 2],
            ['key' => 'diff', 'label' => 'Difference', 'type' => 'number', 'decimals' => 2],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'adjustment', 'label' => 'Stock Opname'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => CountTasks::TONES],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Open', 'where' => ['status' => 'Open'], 'tone' => 'warn'], ['label' => 'Done', 'where' => ['status' => 'Done'], 'tone' => 'success'],
            ['label' => 'Cancelled', 'where' => ['status' => 'Cancelled'], 'tone' => 'neutral']];
    }

    public function filters(): array
    {
        return ['zone' => 'Area', 'status' => 'Status'];
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.wh-count-tasks'] + parent::screen();
    }
}
