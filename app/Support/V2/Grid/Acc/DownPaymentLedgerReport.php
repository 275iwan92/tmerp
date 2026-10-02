<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinDpAllocation;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinVoucher;
use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use App\Services\Acc\CustomerInvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Down Payment Ledger (user 2026-10-02: uang muka jelas dari Customer Order yang mana dan historinya terlacak): satu baris
// per peristiwa uang muka -- ditagih, uang diterima, dipotong di invoice -- dikelompokkan per Customer Order. Jumlah kolom
// Balance per order = uang muka yang sudah diterima dan belum dipotong (CustomerInvoiceService::downPaymentLedger).
class DownPaymentLedgerReport extends AccGrid
{
    private const TONES = ['Down payment invoiced' => 'neutral', 'Money received' => 'success', 'Applied to invoice' => 'info'];

    public function slug(): string
    {
        return 'acc-rpt-dp-ledger';
    }

    public function title(): string
    {
        return 'Down Payment Ledger';
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
        return 'fa-solid fa-money-bill-transfer';
    }

    public function guide(): ?string
    {
        return 'Every down payment event per Customer Order: invoiced, money received, applied to an invoice. '
            .'Balance = money received and not applied yet (still owed to the customer as goods).';
    }

    protected function models(): array
    {
        return [FinInvoice::class, FinVoucher::class, FinDpAllocation::class, CoOrder::class, CustCustomer::class];
    }

    public function search(): string
    {
        return 'Search order no., customer, down payment no...';
    }

    protected function query(): Builder
    {
        return FinInvoice::query()->where('invoice_type', CustomerInvoiceService::DOWN_PAYMENT)->where('doc_status', 'POSTED')->orderByDesc('co_id');
    }

    public function rows(): array
    {
        $dps = $this->query()->limit($this->limit)->get();
        $customers = CustCustomer::withoutGlobalScopes()->whereIn('id_customer', $dps->pluck('partner_id')->unique())->pluck('customer_name', 'id_customer');
        $orders = CoOrder::query()->whereKey($dps->pluck('co_id')->filter()->unique()->all())->pluck('co_no', 'id_order');
        $svc = app(CustomerInvoiceService::class);
        $rows = [];
        foreach ($dps->groupBy('co_id') as $coId => $group) {
            foreach ($svc->downPaymentLedger((int) $coId)['rows'] as $r) {
                $rows[] = ['id' => count($rows) + 1, 'co_no' => $orders[$coId] ?? '—', 'customer' => $customers[$group->first()->partner_id] ?? '—', 'date' => $r['date'],
                    'event' => $r['event'], 'dp_no' => $r['dp_no'], 'doc_no' => $r['doc_no'], 'billed' => $r['billed'], 'in' => $r['in'], 'out' => $r['out'],
                    'balance' => round($r['in'] - $r['out'], 2)];
            }
        }

        return $rows;
    }

    public function columns(): array
    {
        return [
            ['key' => 'co_no', 'label' => 'Customer Order'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'event', 'label' => 'Event', 'type' => 'badge', 'tones' => self::TONES],
            ['key' => 'dp_no', 'label' => 'Down Payment No.'],
            ['key' => 'doc_no', 'label' => 'Document No.'],
            ['key' => 'billed', 'label' => 'Billed', 'type' => 'money', 'sum' => true],
            ['key' => 'in', 'label' => 'Money In', 'type' => 'money', 'sum' => true],
            ['key' => 'out', 'label' => 'Deducted', 'type' => 'money', 'sum' => true],
            ['key' => 'balance', 'label' => 'Balance', 'type' => 'money', 'sum' => true],
        ];
    }

    // Tidak dipakai: baris dibangun dari buku uang muka per order (rows()).
    protected function row(Model $m): array
    {
        return [];
    }

    public function stats(): array
    {
        return array_merge([['label' => 'All']], array_map(fn (string $e) => ['label' => $e, 'where' => ['event' => $e], 'tone' => self::TONES[$e]], array_keys(self::TONES)));
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'co_no' => 'Customer Order', 'event' => 'Event'];
    }

    public function groups(): array
    {
        return ['co_no' => 'Customer Order', 'customer' => 'Customer'];
    }

    public function groupBy(): ?string
    {
        return 'co_no';
    }
}
