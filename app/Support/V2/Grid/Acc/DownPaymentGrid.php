<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinDpOutstanding;
use App\Models\Acc\FinInvoice;
use App\Models\Master\CustCustomer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Down Payment (v1: pages::finance.dp-invoice): tagihan uang muka customer; sisa DP = yang belum dipotongkan ke invoice final
// (view v1_fin_dp_outstanding). New = layar v1.
class DownPaymentGrid extends InvoiceGrid
{
    // id tagihan DP => sisa DP yang belum dipotongkan ke invoice final.
    private array $remaining = [];

    public function slug(): string
    {
        return 'acc-dp-invoice';
    }

    public function title(): string
    {
        return 'Down Payment';
    }

    public function icon(): string
    {
        return 'fa-solid fa-money-bill-wave';
    }

    protected function v1Route(): ?string
    {
        return 'finance.dp-invoice';
    }

    protected function invoiceType(): string
    {
        return 'CUSTOMER_DP';
    }

    protected function partnerLabel(): string
    {
        return 'Customer';
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

    // v1 tidak punya tampilan per tagihan DP (bayar dan potong DP lewat tombol di daftar v1): grid ini daftar + New saja.
    public function openUrl(): ?string
    {
        return null;
    }

    protected function prefetch(Collection $items): void
    {
        parent::prefetch($items);
        $this->remaining = FinDpOutstanding::query()->pluck('dp_remaining', 'dp_invoice_id')->all();
    }

    public function columns(): array
    {
        $cols = parent::columns();
        array_splice($cols, -2, 0, [['key' => 'dp_remaining', 'label' => 'DP Not Yet Applied', 'type' => 'money']]);

        return $cols;
    }

    protected function row(Model $m): array
    {
        return parent::row($m) + ['dp_remaining' => (float) ($this->remaining[$m->invoice_id] ?? $m->total_amount)];
    }
}
