<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\DoLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Customer Order Summary — satu baris per dokumen CO (pengganti v1 "Order with Price - Total Sales" & ringkasan status).
class CoSummaryReport extends CoReportGrid
{
    public function slug(): string
    {
        return 'co-rpt-summary';
    }

    public function title(): string
    {
        return 'Customer Order Summary';
    }

    public function guide(): ?string
    {
        return 'Satu baris = satu CO. Grup per Customer / Bulan / Sales By menjumlahkan Total Qty'
            .($this->seesPrice() ? ' & Nilai Order (= Total Sales)' : '').'. '
            .'Klik tombol Kolom untuk menambah Mode, Rencana Kirim, Sisa, Note, Due Date terdekat.';
    }

    protected function query(): Builder
    {
        return CoOrder::query()->with(['customer', 'salesPerson', 'items'])->orderByDesc('co_date')->orderByDesc('id_order');
    }

    protected function prefetch(Collection $items): void
    {
        $this->shipped = DoLine::query()->whereIn('co_order_id', $items->modelKeys())
            ->whereHas('delivery', fn ($q) => $q->where('status', 'SHIPPED'))->groupBy('co_order_id')
            ->selectRaw('co_order_id, SUM(qty_do) q')->pluck('q', 'co_order_id')->all();
        $this->planned = CoOrderDelivery::query()->whereIn('order_id', $items->modelKeys())->groupBy('order_id')
            ->selectRaw('order_id, SUM(ship_qty) q')->pluck('q', 'order_id')->all();
    }

    protected function row(Model $m): array
    {
        $qty = (float) $m->items->sum('qty');
        $shipped = (float) ($this->shipped[$m->getKey()] ?? 0);
        $status = $this->status($m);

        return [
            'co' => $m->co_no, 'co_date' => $m->co_date ? substr((string) $m->co_date, 0, 10) : null,
            'month' => $m->co_date ? substr((string) $m->co_date, 0, 7) : null, 'customer' => $m->customer?->customer_name,
            'po' => $m->customer_po_no, 'confirm' => $m->doc_status === 'CONFIRMED' ? 'Yes' : 'No', 'status' => $status,
            'sales' => $m->salesPerson?->sales_name, 'mode' => $m->entry_mode, 'currency' => $m->currency_code, 'note' => $m->notes,
            'items' => $m->items->count(), 'qty' => $qty, 'value' => (float) $m->items->sum('subtotal'),
            'planned' => (float) ($this->planned[$m->getKey()] ?? 0), 'shipped' => $shipped, 'remain' => max(0, $qty - $shipped),
            'progress' => $qty > 0 ? (int) round(min($shipped, $qty) / $qty * 100) : 0,
            'due' => optional($m->items->pluck('due_date')->filter()->sort()->first(), fn ($d) => substr((string) $d, 0, 10)),
            'open' => ! $m->stat_cancel && ! in_array($m->doc_status, ['CLOSED', 'CANCELLED'], true),
            'outstanding' => ! $m->stat_cancel && $m->doc_status === 'CONFIRMED' && $qty > $shipped,
        ];
    }

    public function columns(): array
    {
        $hidden = ['visible' => false];

        return [
            ['key' => 'co', 'label' => 'No CO'],
            ['key' => 'co_date', 'label' => 'Tgl CO', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'po', 'label' => 'No PO Customer'],
            ['key' => 'sales', 'label' => 'Sales By'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
            ['key' => 'items', 'label' => 'Item', 'type' => 'number'],
            ['key' => 'qty', 'label' => 'Total Qty', 'type' => 'number', 'sum' => true],
            ['key' => 'value', 'label' => 'Nilai Order', 'type' => 'money', 'sum' => true],
            ['key' => 'shipped', 'label' => 'Terkirim', 'type' => 'number', 'sum' => true],
            ['key' => 'progress', 'label' => '% Kirim', 'type' => 'progress'],
            ['key' => 'remain', 'label' => 'Sisa', 'type' => 'number', 'sum' => true] + $hidden,
            ['key' => 'planned', 'label' => 'Rencana Kirim', 'type' => 'number', 'sum' => true] + $hidden,
            ['key' => 'confirm', 'label' => 'Confirm', 'type' => 'badge', 'tones' => ['Yes' => 'success', 'No' => 'neutral']] + $hidden,
            ['key' => 'mode', 'label' => 'Mode CO', 'type' => 'badge', 'tones' => ['INTEGRATED' => 'info', 'INSTANT' => 'accent']] + $hidden,
            ['key' => 'currency', 'label' => 'Mata Uang'] + $hidden,
            ['key' => 'month', 'label' => 'Bulan'] + $hidden,
            ['key' => 'due', 'label' => 'Due Date terdekat', 'type' => 'date'] + $hidden,
            ['key' => 'note', 'label' => 'Note'] + $hidden,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'Semua CO'],
            ['label' => 'Open & tidak batal', 'where' => ['open' => true], 'tone' => 'info'],
            ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'],
            ['label' => 'Outstanding', 'where' => ['outstanding' => true], 'tone' => 'warn'],
            ['label' => 'Batal', 'where' => ['status' => 'CANCELLED'], 'tone' => 'danger'],
        ];
    }
}
