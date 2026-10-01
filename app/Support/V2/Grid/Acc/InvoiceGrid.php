<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinInvoice;
use App\Models\Sdi\CoOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Induk daftar tagihan (tm2acc.fin_invoice): piutang customer, uang muka customer, tagihan supplier. Satu turunan = satu
// invoice_type. Status = gabungan status dokumen + pembayaran + jatuh tempo, aturan yang sama dengan layar v1.
abstract class InvoiceGrid extends AccGrid
{
    protected const STATUS_TONES = ['Draft' => 'neutral', 'Pending approval' => 'warn', 'Open' => 'info', 'Partially paid' => 'info', 'Due this week' => 'warn',
        'Overdue' => 'danger', 'Paid' => 'success', 'Cancelled' => 'danger'];

    // id partner => nama (customer / supplier).
    protected array $partners = [];

    private array $orders = [];

    abstract protected function invoiceType(): string;

    // Nama pihak untuk sekumpulan id (lintas-DB tm2acc -> tm2mst, satu query).
    abstract protected function partnerNames(array $ids): array;

    abstract protected function partnerLabel(): string;

    // Tagihan ke customer punya cetakan (sales-invoice); tagihan supplier tidak dicetak dari sini.
    protected function printable(): bool
    {
        return false;
    }

    public function actionUrls(): array
    {
        return $this->printable() ? ['print' => $this->printUrl('sales-invoice')] : [];
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Transaction Flows';
    }

    protected function models(): array
    {
        return [FinInvoice::class];
    }

    protected function query(): Builder
    {
        return FinInvoice::query()->where('invoice_type', $this->invoiceType())->orderByDesc('invoice_date')->orderByDesc('invoice_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->partners = $this->partnerNames($items->pluck('partner_id')->filter()->unique()->values()->all());
        $this->orders = CoOrder::query()->whereKey($items->pluck('co_id')->filter()->unique()->all())->pluck('co_no', 'id_order')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'invoice_no', 'label' => 'Invoice No. / '.$this->partnerLabel(), 'type' => 'doc', 'party' => 'partner'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'co_no', 'label' => 'Customer Order', 'visible' => false],
            ['key' => 'tax', 'label' => 'VAT', 'type' => 'money', 'visible' => false],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
            // Dengan tombol Print, Paid disembunyikan supaya baris induk tetap paling banyak 8 kolom (rulebook C.28).
            ['key' => 'paid', 'label' => 'Paid', 'type' => 'money', 'visible' => ! $this->printable()],
            ['key' => 'outstanding', 'label' => 'Outstanding', 'type' => 'money'],
            ['key' => 'days_overdue', 'label' => 'Days Overdue', 'type' => 'number', 'visible' => false],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
            ...($this->printable() ? [$this->printColumn()] : []),
        ];
    }

    protected function row(Model $m): array
    {
        $open = $m->doc_status === 'POSTED' && $m->payment_status !== 'PAID';
        $late = $open && $m->due_date ? (int) $m->due_date->diffInDays(today(), false) : 0;

        return [
            // Nomor terbit saat tagihan disimpan; baris tanpa nomor ditandai id-nya supaya persegi dokumen tidak kosong.
            'invoice_no' => $m->invoice_number ?: 'INV-'.$m->invoice_id, 'partner' => $this->partners[$m->partner_id] ?? null,
            'date' => $m->invoice_date?->toDateString(), 'due' => $m->due_date?->toDateString(), 'description' => $m->description,
            'co_no' => $this->orders[$m->co_id] ?? null, 'tax' => (float) $m->tax_amount, 'total' => (float) $m->total_amount, 'paid' => (float) $m->paid_amount,
            'outstanding' => $m->doc_status === 'POSTED' ? $m->outstanding() : 0.0, 'days_overdue' => $late > 0 ? $late : null, 'status' => $this->status($m),
        ];
    }

    protected function status(FinInvoice $i): string
    {
        return match (true) {
            $i->doc_status === 'CANCELLED' => 'Cancelled',
            $i->doc_status === 'SUBMITTED' => 'Pending approval',
            $i->doc_status !== 'POSTED' => 'Draft',
            $i->payment_status === 'PAID' => 'Paid',
            $i->due_date && $i->due_date->lt(today()) => 'Overdue',
            $i->due_date && $i->due_date->lte(today()->addDays(7)) => 'Due this week',
            default => $i->payment_status === 'PARTIAL' ? 'Partially paid' : 'Open',
        };
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['status' => ['Draft', 'Pending approval']], 'tone' => 'neutral'],
            ['label' => 'Open', 'where' => ['status' => ['Open', 'Partially paid', 'Due this week']], 'tone' => 'info'],
            ['label' => 'Overdue', 'where' => ['status' => 'Overdue'], 'tone' => 'danger'],
            ['label' => 'Paid', 'where' => ['status' => 'Paid'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['partner' => $this->partnerLabel(), 'status' => 'Status'];
    }
}
