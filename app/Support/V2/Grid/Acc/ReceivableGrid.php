<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinInvoice;
use App\Models\Master\CustCustomer;

// Order to Cash (v1: pages::finance.receivables): tagihan penjualan ke customer + penerimaan pembayarannya. Buka / New = layar v1.
class ReceivableGrid extends InvoiceGrid
{
    public function slug(): string
    {
        return 'acc-receivables';
    }

    public function title(): string
    {
        return 'Order to Cash';
    }

    public function icon(): string
    {
        return 'fa-solid fa-hand-holding-dollar';
    }

    protected function v1Route(): ?string
    {
        return 'finance.receivables';
    }

    protected function invoiceType(): string
    {
        return 'CUSTOMER_INVOICE';
    }

    protected function partnerLabel(): string
    {
        return 'Customer';
    }

    protected function printable(): bool
    {
        return true;
    }

    protected function models(): array
    {
        return [FinInvoice::class, CustCustomer::class];
    }

    public function search(): string
    {
        return 'Search invoice no, customer, description...';
    }

    // Tanpa saringan data dummy / legacy: tagihan lama tetap harus terbaca nama pihaknya (sama dengan v1).
    protected function partnerNames(array $ids): array
    {
        return CustCustomer::withoutGlobalScopes()->whereIn('id_customer', $ids)->pluck('customer_name', 'id_customer')->all();
    }
}
