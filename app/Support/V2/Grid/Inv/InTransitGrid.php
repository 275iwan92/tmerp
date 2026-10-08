<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Inv;

use App\Support\V2\Data\InTransit;
use App\Support\V2\Grid\DocListGrid;

// Inventory > Warehouse > In Transit: transfer dua langkah yang sudah dikirim dengan umurnya. Open = panel Receive / penyelesaian selisih
// (resources/views/v2/screens/wh-in-transit.blade.php). Transfer baru dibuat di Stock Transfer (pilihan Two-step).
class InTransitGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'wh-in-transit';
    }

    public function title(): string
    {
        return 'In Transit';
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
        return 'fa-solid fa-truck-ramp-box';
    }

    protected function listRows(): array
    {
        return InTransit::rows();
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_transfer', 'tm2inv.inv_transfer_line', 'tm2mst.wh_warehouse', 'tm2mst.wh_loc'];
    }

    public function search(): string
    {
        return 'Search transfer no., warehouse, item, bin...';
    }

    public function guide(): ?string
    {
        return 'Two-step transfers that left the source bin and are not received yet. Open a row to receive it (enter what arrived) or to settle a difference as loss or return to the source bin.';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Transfer No. / Route', 'type' => 'doc', 'party' => 'route'],
            ['key' => 'shipped', 'label' => 'Shipped', 'type' => 'datetime'],
            ['key' => 'age', 'label' => 'Age (days)', 'type' => 'number', 'decimals' => 1],
            ['key' => 'from', 'label' => 'From Warehouse'],
            ['key' => 'to', 'label' => 'To Warehouse'],
            ['key' => 'carried_by', 'label' => 'Carried By'],
            ['key' => 'short', 'label' => 'Short Qty', 'type' => 'number', 'decimals' => 2],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => InTransit::TONES],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Sent', 'type' => 'number'],
            ['key' => 'received', 'label' => 'Received', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'from_bin', 'label' => 'From Bin'],
            ['key' => 'to_bin', 'label' => 'To Bin'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'In transit', 'where' => ['status' => 'In transit'], 'tone' => 'info'], ['label' => 'Discrepancy', 'where' => ['status' => 'Discrepancy'], 'tone' => 'danger'],
            ['label' => 'Received', 'where' => ['status' => 'Received'], 'tone' => 'success'], ['label' => 'Closed', 'where' => ['status' => 'Closed'], 'tone' => 'neutral']];
    }

    public function filters(): array
    {
        return ['from' => 'From Warehouse', 'to' => 'To Warehouse', 'status' => 'Status'];
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.wh-in-transit'] + parent::screen();
    }
}
