<?php

namespace App\Support\V2\Grid\Sales;

use App\Support\V2\Data\CustomerOrder;
use App\Support\V2\Grid\DocListGrid;

// Daftar Customer Order (Grid Transactional) di layar v2 customer-order: Open / + New Record = form di layar yang sama.
class CustomerOrderListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'customer-order';
    }

    public function title(): string
    {
        return 'Customer Order';
    }

    public function module(): string
    {
        return 'SALES';
    }

    protected function listId(): string
    {
        return 'co-list';
    }

    protected function listRows(): array
    {
        return CustomerOrder::rows();
    }

    protected function sources(): array
    {
        return ['tm2sdi.co_order', 'tm2sdi.co_order_item', 'tm2sdi.co_order_external_cost', 'tm2sdi.quo_quotation.quotation_no'];
    }

    public function search(): string
    {
        return 'Search CO no., customer, PO no., quotation...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'CO No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'date', 'label' => t('Date'), 'type' => 'date'],
            ['key' => 'mode', 'label' => 'Path', 'type' => 'badge', 'tones' => CustomerOrder::MODE_TONES],
            ['key' => 'quotation', 'label' => 'Quotation'],
            ['key' => 'po', 'label' => 'Customer PO No.'],
            ['key' => 'due', 'label' => 'Nearest Due Date', 'type' => 'date'],
            ['key' => 'total', 'label' => t('Total due'), 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => CustomerOrder::STATUS_TONES],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Product', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'spec', 'label' => 'Specification'],
            ['key' => 'kind', 'label' => 'Line Type'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => t('All')],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => t('Waiting for approval'), 'where' => ['status' => 'SUBMITTED'], 'tone' => 'warn'],
            ['label' => 'Approved', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'],
            ['label' => 'Rejected / Cancelled', 'where' => ['status' => ['REJECTED', 'CANCELLED']], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['mode' => 'Path', 'status' => 'Status'];
    }
}
