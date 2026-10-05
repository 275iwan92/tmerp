<?php

namespace App\Support\V2\Grid\Sales;

use App\Support\V2\Data\DeliveryOrder;
use App\Support\V2\Grid\DocListGrid;

// Daftar Delivery Notes (Grid Transactional) di layar v2 delivery-notes: Open / + New Record = form di layar yang sama.
class DeliveryNoteListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'delivery-notes';
    }

    public function title(): string
    {
        return 'Delivery Notes';
    }

    public function module(): string
    {
        return 'SALES';
    }

    protected function listId(): string
    {
        return 'sj-list';
    }

    protected function listRows(): array
    {
        return DeliveryOrder::rows(['CONFIRMED', 'SHIPPED']);
    }

    protected function sources(): array
    {
        return ['tm2sdi.do_delivery (CONFIRMED / SHIPPED)'];
    }

    public function search(): string
    {
        return 'Search delivery note no., customer, address...';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Delivery Note No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'date', 'label' => t('Date'), 'type' => 'date'],
            ['key' => 'ship', 'label' => t('Planned Shipment'), 'type' => 'date'],
            ['key' => 'shipped', 'label' => t('Shipped'), 'type' => 'date'],
            ['key' => 'address', 'label' => 'Destination'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => DeliveryOrder::STATUS_TONES],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Product', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'co', 'label' => 'CO No.'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => t('All')], ['label' => t('Ready to ship'), 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'], ['label' => 'Shipped', 'where' => ['status' => 'SHIPPED'], 'tone' => 'info']];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'status' => 'Status'];
    }
}
