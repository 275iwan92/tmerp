<?php

namespace App\Support\V2\Grid\Acc;

use App\Support\V2\Data\CustomerInvoice;
use App\Support\V2\Grid\DocListGrid;

// Daftar Customer Invoice (Grid Transactional) di layar v2 customer-invoice: Open / + New Record = form di layar yang sama.
class CustomerInvoiceListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'customer-invoice';
    }

    public function title(): string
    {
        return 'Customer Invoice';
    }

    public function module(): string
    {
        return 'FINACC';
    }

    protected function listRows(): array
    {
        return CustomerInvoice::rows();
    }

    protected function sources(): array
    {
        return ['tm2acc.fin_invoice', 'tm2acc.fin_invoice_line', 'tm2mst.cust_customer', 'tm2sdi.co_order'];
    }

    public function search(): string
    {
        return 'Search invoice no., customer, order no...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Invoice No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'kind', 'label' => 'Type', 'visible' => false],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'co_no', 'label' => 'Customer Order'],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
            ['key' => 'dp', 'label' => 'Down Payment', 'type' => 'money'],
            ['key' => 'outstanding', 'label' => 'Outstanding', 'type' => 'money'],
            ['key' => 'state', 'label' => 'Status', 'type' => 'badge', 'tones' => CustomerInvoice::STATE_TONES],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'dn', 'label' => 'Delivery Note'],
            ['key' => 'received', 'label' => 'Qty Received', 'type' => 'number'],
            ['key' => 'invoiced', 'label' => 'Qty Invoiced', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'price', 'label' => 'Price', 'type' => 'money'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Draft', 'where' => ['state' => 'Draft'], 'tone' => 'neutral'],
            ['label' => 'Open', 'where' => ['state' => ['Open', 'Partially paid', 'Due this week']], 'tone' => 'info'],
            ['label' => 'Overdue', 'where' => ['state' => 'Overdue'], 'tone' => 'danger'], ['label' => 'Paid', 'where' => ['state' => 'Paid'], 'tone' => 'success'],
            ['label' => 'Cancelled', 'where' => ['state' => 'Cancelled'], 'tone' => 'danger'],
            ['label' => 'Down Payment', 'where' => ['kind' => 'Down Payment'], 'tone' => 'warn']];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'kind' => 'Type', 'state' => 'Status'];
    }

    public function rowTone(): ?string
    {
        return 'tone';
    }
}
