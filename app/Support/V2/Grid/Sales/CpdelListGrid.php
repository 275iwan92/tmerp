<?php

namespace App\Support\V2\Grid\Sales;

use App\Services\Sales\CpdelPromiseService as Promise;
use App\Support\V2\Data\Cpdel;
use App\Support\V2\Grid\DocListGrid;

// Daftar Customer Plan Delivery (layar v2 cpdel): turunan DocListGrid, kolom dan saringannya sama dengan sebelumnya.
class CpdelListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'cpdel';
    }

    public function title(): string
    {
        return 'Customer Plan Delivery';
    }

    public function module(): string
    {
        return 'SALES';
    }

    protected function listId(): string
    {
        return 'cpdel-list';
    }

    protected function listRows(): array
    {
        return Cpdel::rows();
    }

    protected function sources(): array
    {
        return ['tm2sdi.co_order_delivery', 'tm2sdi.co_order (CONFIRMED)', 'tm2prd.prd_wo.source_delivery_id'];
    }

    public function search(): string
    {
        return 'Search CO no., customer, product...';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'co', 'label' => 'CO No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'item', 'label' => 'Product'],
            ['key' => 'slot', 'label' => 'Customer slot', 'type' => 'date'],
            ['key' => 'time', 'label' => 'Time'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'toneKey' => 'due_tone'],
            ['key' => 'ready', 'label' => 'Production ready'],
            ['key' => 'gap', 'label' => 'Gap (days)', 'type' => 'number', 'visible' => false],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'sum' => true],
            ['key' => 'original', 'label' => 'Original promise', 'type' => 'date', 'visible' => false],
            ['key' => 'production', 'label' => 'Production'],
            ['key' => 'state', 'label' => 'Promise', 'type' => 'badge', 'tones' => Promise::STATES],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Product', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'spec', 'label' => 'Specification'],
            ['key' => 'order_qty', 'label' => 'Order Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'ship_qty', 'label' => 'This Shipment', 'type' => 'number'],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Overdue', 'where' => ['bucket' => 'OVERDUE'], 'tone' => 'danger'],
            ['label' => 'Today', 'where' => ['bucket' => 'TODAY'], 'tone' => 'tangerine'],
            ['label' => 'Due soon', 'where' => ['bucket' => 'SOON'], 'tone' => 'yellow'],
            ['label' => 'Next 7 days', 'where' => ['week' => 1], 'tone' => 'accent'],
        ];
    }

    public function tabs(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Waiting for PPIC', 'where' => ['state' => 'WAITING_PPIC'], 'tone' => 'neutral'],
            ['label' => 'Need negotiation', 'where' => ['state' => 'NEED_NEGOTIATION'], 'tone' => 'warn'],
            ['label' => 'Escalated', 'where' => ['state' => 'ESCALATED'], 'tone' => 'danger'],
            ['label' => 'Aligned', 'where' => ['state' => 'ALIGNED'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'state' => 'Promise'];
    }

    public function rowTone(): ?string
    {
        return 'row_tone';
    }

    public function groupBy(): ?string
    {
        return 'co';
    }
}
