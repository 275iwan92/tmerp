<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Inv;

use App\Support\V2\Data\PickLists;
use App\Support\V2\Grid\DocListGrid;

// Daftar Pick List (Grid Transactional) di Inventory > Warehouse > Pick List: Open = form pick list, + New Record = pilih Work Order
// yang bookingnya sudah Confirm. Form dan kartu bahan: resources/views/v2/screens/wh-pick-list.blade.php.
class PickListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'wh-pick-list';
    }

    public function title(): string
    {
        return 'Pick List';
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
        return 'fa-solid fa-list-check';
    }

    protected function listRows(): array
    {
        return PickLists::rows();
    }

    protected function sources(): array
    {
        return ['tm2inv.wh_pick_list', 'tm2inv.wh_pick_list_line', 'tm2inv.rm_booking_d', 'tm2inv.inv_pallet', 'tm2inv.inv_batch', 'tm2mst.wh_loc'];
    }

    public function search(): string
    {
        return 'Search pick list, work order, item, pallet...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Pick List No. / Work Order', 'type' => 'doc', 'party' => 'wo'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'need', 'label' => 'Needed By', 'type' => 'date'],
            ['key' => 'booking', 'label' => 'Booking'],
            ['key' => 'co', 'label' => 'Customer Order'],
            ['key' => 'deliver_to', 'label' => 'Deliver To'],
            ['key' => 'short', 'label' => 'Check', 'type' => 'badge', 'tones' => ['Short' => 'danger']],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => PickLists::STATUS_TONES],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'pallet', 'label' => 'Pallet'],
            ['key' => 'bin', 'label' => 'Bin'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Draft', 'where' => ['status' => 'Draft'], 'tone' => 'neutral'], ['label' => 'In progress', 'where' => ['status' => 'In progress'], 'tone' => 'warn'],
            ['label' => 'Done', 'where' => ['status' => 'Done'], 'tone' => 'success'], ['label' => 'Cancelled', 'where' => ['status' => 'Cancelled'], 'tone' => 'danger']];
    }

    public function tabs(): array
    {
        return [['label' => 'All'], ['label' => 'Short', 'where' => ['short' => 'Short']]];
    }

    public function filters(): array
    {
        return ['wo' => 'Work Order', 'deliver_to' => 'Deliver To', 'status' => 'Status'];
    }

    public function rowTone(): ?string
    {
        return 'tone';
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.wh-pick-list'] + parent::screen();
    }
}
