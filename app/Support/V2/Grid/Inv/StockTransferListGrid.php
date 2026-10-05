<?php

namespace App\Support\V2\Grid\Inv;

use App\Support\V2\Data\StockTransfer;
use App\Support\V2\Grid\DocListGrid;

// Daftar Stock Transfer (Grid Transactional) di layar v2 stock-transfer: Open / + New Record = form di layar yang sama.
class StockTransferListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'stock-transfer';
    }

    public function title(): string
    {
        return 'Stock Transfer';
    }

    public function module(): string
    {
        return 'INV';
    }

    protected function listRows(): array
    {
        return StockTransfer::rows();
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_transfer', 'tm2inv.inv_transfer_line', 'tm2mst.wh_warehouse', 'tm2mst.wh_loc'];
    }

    public function search(): string
    {
        return 'Search transfer no., warehouse, item, bin...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Transfer No. / Route', 'type' => 'doc', 'party' => 'route'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'from', 'label' => 'From Warehouse'],
            ['key' => 'to', 'label' => 'To Warehouse'],
            ['key' => 'reason', 'label' => 'Reason'],
            ['key' => 'carried_by', 'label' => 'Carried By'],
            ['key' => 'dead', 'label' => 'Dead Stock', 'type' => 'badge', 'tones' => ['Dead stock' => 'danger']],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => StockTransfer::STATUS_TONES],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'from_bin', 'label' => 'From Bin'],
            ['key' => 'to_bin', 'label' => 'To Bin'],
        ]];
    }

    public function stats(): array
    {
        return array_values(array_filter([['label' => 'All'], ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            StockTransfer::approvalActive() ? ['label' => 'Waiting for approval', 'where' => ['status' => 'SUBMITTED'], 'tone' => 'warn'] : null,
            ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'], ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'danger']]));
    }

    public function tabs(): array
    {
        return [['label' => 'All'], ['label' => 'Dead stock', 'where' => ['dead' => 'Dead stock']]];
    }

    public function filters(): array
    {
        return ['from' => 'From Warehouse', 'to' => 'To Warehouse', 'reason' => 'Reason', 'status' => 'Status'];
    }

    public function rowTone(): ?string
    {
        return 'tone';
    }
}
