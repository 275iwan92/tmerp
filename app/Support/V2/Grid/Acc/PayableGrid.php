<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinInvoice;
use App\Models\Master\SupSupplier;

// Procure to Pay (v1: pages::finance.payables): tagihan supplier (dari SPB) + persetujuan + pembayarannya. Buka / New = layar v1.
class PayableGrid extends InvoiceGrid
{
    public function slug(): string
    {
        return 'acc-payables';
    }

    public function title(): string
    {
        return 'Procure to Pay';
    }

    public function icon(): string
    {
        return 'fa-solid fa-file-invoice-dollar';
    }

    protected function v1Route(): ?string
    {
        return 'finance.payables';
    }

    protected function invoiceType(): string
    {
        return 'SUPPLIER_BILL';
    }

    protected function partnerLabel(): string
    {
        return 'Supplier';
    }

    protected function models(): array
    {
        return [FinInvoice::class, SupSupplier::class];
    }

    public function search(): string
    {
        return 'Search invoice no, supplier, description...';
    }

    // Tanpa saringan data dummy / legacy: tagihan lama tetap harus terbaca nama pihaknya (sama dengan v1).
    protected function partnerNames(array $ids): array
    {
        return SupSupplier::withoutGlobalScopes()->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();
    }
}
