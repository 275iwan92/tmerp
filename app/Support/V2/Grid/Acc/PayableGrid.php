<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinInvoice;
use App\Models\Master\SupSupplier;

/*
 * REMARK
 * Tujuan       : Grid Procure to Pay: tagihan supplier (dari penerimaan barang / jasa) dengan status, jatuh tempo dan pembayarannya; Open dan + New Record membuka form v2 acc-payables-form (New = kartu Start).
 * Sumber hulu  : tm2acc.fin_invoice (SUPPLIER_BILL), nama supplier dari tm2mst.sup_supplier (query terpisah, tanpa saringan data dummy / legacy).
 * Input        : Pencarian, statistik status, filter.
 * Output       : Baris grid.
 * Dipakai oleh : Menu FIN_AP (v2:acc-payables), layar acc-payables-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::finance.payables sudah dipindah ke tmerp_unused (aturan D.25).
 */
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

    protected function canCreate(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'acc-payables-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'acc-payables-form').'?new=1';
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
