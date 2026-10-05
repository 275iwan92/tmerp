<?php

namespace App\Support\V2\Grid\Sales;

use App\Support\V2\Data\DeliveryOrder;
use App\Support\V2\Grid\DocListGrid;

// Daftar Delivery Order (Grid Transactional) di layar v2 delivery-order: Open / + New Record = form di layar yang sama.
class DeliveryOrderListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'delivery-order';
    }

    public function title(): string
    {
        return 'Delivery Order';
    }

    public function module(): string
    {
        return 'SALES';
    }

    protected function listId(): string
    {
        return 'do-list';
    }

    protected function listRows(): array
    {
        return DeliveryOrder::rows();
    }

    protected function sources(): array
    {
        return ['tm2sdi.do_delivery', 'tm2sdi.do_line'];
    }

    public function search(): string
    {
        return 'Search DO no., customer, address...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'DO No. / Customer', 'type' => 'doc', 'party' => 'customer'],
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
        return [['label' => t('All')], ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'], ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'], ['label' => 'Shipped', 'where' => ['status' => 'SHIPPED'], 'tone' => 'info']];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'status' => 'Status'];
    }
}
