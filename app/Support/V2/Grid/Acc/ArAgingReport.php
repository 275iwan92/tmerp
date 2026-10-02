<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinInvoice;
use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use App\Services\Acc\CustomerInvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// AR Aging (pengganti tabel umur piutang layar Order to Cash lama): invoice customer Confirmed yang belum lunas, sisa
// tagihannya ditaruh di ember umur menurut hari lewat jatuh tempo per HARI INI. Uang muka bukan piutang, tidak ikut.
class ArAgingReport extends AccGrid
{
    private const BUCKETS = ['not_due' => 'Not Due', 'd30' => '1-30 Days', 'd60' => '31-60 Days', 'd90' => '61-90 Days', 'over' => 'Over 90 Days'];

    private const TONES = ['Not Due' => 'success', '1-30 Days' => 'info', '31-60 Days' => 'warn', '61-90 Days' => 'warn', 'Over 90 Days' => 'danger'];

    private array $customers = [];

    private array $orders = [];

    public function slug(): string
    {
        return 'acc-rpt-ar-aging';
    }

    public function title(): string
    {
        return 'AR Aging';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function group(): string
    {
        return 'Financial Reports';
    }

    public function icon(): string
    {
        return 'fa-solid fa-hourglass-half';
    }

    public function guide(): ?string
    {
        return 'Unpaid confirmed invoices by age past the due date, as of today. Group by Customer for the total per customer.';
    }

    protected function models(): array
    {
        return [FinInvoice::class, CustCustomer::class, CoOrder::class];
    }

    public function search(): string
    {
        return 'Search invoice no., customer...';
    }

    protected function query(): Builder
    {
        return FinInvoice::query()->where('invoice_type', CustomerInvoiceService::INVOICE)->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID')
            ->orderBy('due_date')->orderBy('invoice_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->customers = CustCustomer::withoutGlobalScopes()->whereIn('id_customer', $items->pluck('partner_id')->unique())->pluck('customer_name', 'id_customer')->all();
        $this->orders = CoOrder::query()->whereKey($items->pluck('co_id')->filter()->unique()->all())->pluck('co_no', 'id_order')->all();
    }

    public function columns(): array
    {
        return array_merge([
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'no', 'label' => 'Invoice No.'],
            ['key' => 'co_no', 'label' => 'Customer Order', 'visible' => false],
            ['key' => 'tax', 'label' => 'Tax Identity', 'visible' => false],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'days', 'label' => 'Days Overdue', 'type' => 'number'],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sum' => true, 'visible' => false],
            ['key' => 'outstanding', 'label' => 'Outstanding', 'type' => 'money', 'sum' => true],
        ], array_map(fn (string $key, string $label) => ['key' => $key, 'label' => $label, 'type' => 'money', 'sum' => true], array_keys(self::BUCKETS), self::BUCKETS), [
            ['key' => 'age', 'label' => 'Age', 'type' => 'badge', 'tones' => self::TONES],
        ]);
    }

    protected function row(Model $m): array
    {
        $left = max(0.0, $m->outstanding());
        $days = $m->due_date ? max(0, (int) $m->due_date->copy()->startOfDay()->diffInDays(now()->startOfDay(), false)) : 0;
        $bucket = match (true) {
            $days <= 0 => 'not_due',
            $days <= 30 => 'd30',
            $days <= 60 => 'd60',
            $days <= 90 => 'd90',
            default => 'over',
        };

        return ['customer' => $this->customers[$m->partner_id] ?? '—', 'no' => $m->invoice_number, 'co_no' => $this->orders[$m->co_id] ?? null, 'tax' => $m->is_ppn ? 'PPN' : 'Non PPN',
            'date' => $m->invoice_date?->toDateString(), 'due' => $m->due_date?->toDateString(), 'days' => $days, 'total' => (float) $m->total_amount, 'outstanding' => $left,
            'age' => self::BUCKETS[$bucket]] + array_map(fn () => 0.0, self::BUCKETS);
    }

    public function rows(): array
    {
        // Sisa tagihan masuk tepat satu ember umur.
        return array_map(function (array $r) {
            $r[array_search($r['age'], self::BUCKETS, true)] = $r['outstanding'];

            return $r;
        }, parent::rows());
    }

    public function stats(): array
    {
        return array_merge([['label' => 'All']], array_map(fn (string $label) => ['label' => $label, 'where' => ['age' => $label], 'tone' => self::TONES[$label]], array_values(self::BUCKETS)));
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'tax' => 'Tax Identity', 'age' => 'Age'];
    }

    public function groups(): array
    {
        return ['customer' => 'Customer', 'age' => 'Age'];
    }

    public function groupBy(): ?string
    {
        return 'customer';
    }
}
