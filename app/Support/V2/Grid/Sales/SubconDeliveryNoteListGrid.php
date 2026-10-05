<?php

namespace App\Support\V2\Grid\Sales;

use App\Support\V2\Data\SubconDelivery;
use App\Support\V2\Grid\DocListGrid;

// Daftar Subcontract Delivery Note (Grid Transactional) di layar v2 subcon-delivery-note: Open / + New Record = form di layar yang sama.
class SubconDeliveryNoteListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'subcon-delivery-note';
    }

    public function title(): string
    {
        return 'Subcontract Delivery Note';
    }

    public function module(): string
    {
        return 'SALES';
    }

    protected function listId(): string
    {
        return 'subcon-dn-list';
    }

    protected function listRows(): array
    {
        return SubconDelivery::rows();
    }

    protected function sources(): array
    {
        return ['tm2sdi.do_delivery', 'tm2sdi.do_line', 'tm2mst.sup_supplier'];
    }

    public function search(): string
    {
        return 'Search note no., subcontractor, process, CO...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Note No. / Subcontractor', 'type' => 'doc', 'party' => 'supplier'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'process', 'label' => 'Process'],
            ['key' => 'co', 'label' => 'Customer Order'],
            ['key' => 'pr', 'label' => 'PR'],
            ['key' => 'ship', 'label' => 'Planned Shipment', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => SubconDelivery::STATUS_TONES],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'item', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'co', 'label' => 'CO No.'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'], ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'], ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'danger']];
    }

    public function filters(): array
    {
        return ['supplier' => 'Subcontractor', 'process' => 'Process', 'status' => 'Status'];
    }
}
