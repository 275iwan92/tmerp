<?php

namespace App\Support\V2\Grid\Acc;

use App\Support\V2\Data\SupplierInvoiceReceipt;
use App\Support\V2\Grid\DocListGrid;

// Daftar Supplier Invoice Receipt (Grid Transactional) di layar v2 supplier-invoice-receipt: Open / + New Record = form di layar yang sama.
class SupplierInvoiceReceiptListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'supplier-invoice-receipt';
    }

    public function title(): string
    {
        return 'Supplier Invoice Receipt';
    }

    public function module(): string
    {
        return 'FINACC';
    }

    protected function listRows(): array
    {
        return SupplierInvoiceReceipt::rows();
    }

    protected function sources(): array
    {
        return ['tm2acc.fin_invoice_receipt', 'tm2acc.fin_invoice_receipt_line', 'tm2mst.sup_supplier'];
    }

    public function search(): string
    {
        return 'Search receipt no., supplier, invoice no...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Receipt No. / Supplier', 'type' => 'doc', 'party' => 'supplier'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'handed_by', 'label' => 'Handed Over By'],
            ['key' => 'planned', 'label' => 'Planned Payment', 'type' => 'date'],
            ['key' => 'method', 'label' => 'Payment Method'],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => SupplierInvoiceReceipt::STATUS_TONES],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Invoice', 'columns' => [
            ['key' => 'invoice', 'label' => 'Supplier Invoice No.', 'strong' => true],
            ['key' => 'invoice_date', 'label' => 'Invoice Date', 'type' => 'date'],
            ['key' => 'po', 'label' => 'PO No.'],
            ['key' => 'tax', 'label' => 'Tax Invoice No.'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'], ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'], ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'danger']];
    }

    public function filters(): array
    {
        return ['supplier' => 'Supplier', 'status' => 'Status'];
    }
}
