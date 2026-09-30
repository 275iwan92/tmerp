<?php

namespace App\Support\V2\Grid\Pur;

use App\Models\Master\SupSupplier;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Outstanding PO (v1: pages::pur.report.outstanding-po): baris PO SENT/PARTIALLY_RECEIVED yang masih menunggu barang.
// Aturan sama dengan v1: hanya baris is_receivable, nilai sisa = porsi sisa qty x c_line_net.
class OutstandingPoReport extends PurReportGrid
{
    private array $suppliers = [];

    public function slug(): string
    {
        return 'pur-rpt-outstanding-po';
    }

    public function title(): string
    {
        return 'Outstanding PO';
    }

    public function group(): string
    {
        return 'Order › Report';
    }

    protected function models(): array
    {
        return [PoLine::class, PoOrder::class, SupSupplier::class];
    }

    public function search(): string
    {
        return 'Search PO no, supplier, item...';
    }

    protected function query(): Builder
    {
        return PoLine::query()->with('order')->where('is_receivable', true)
            ->whereRaw('i_qty - COALESCE(c_qty_received, 0) > 0')
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['SENT', 'PARTIALLY_RECEIVED'])->where('stat_cancel', false))
            ->orderBy('po_id')->orderBy('line_no');
    }

    protected function prefetch(Collection $items): void
    {
        $this->suppliers = SupSupplier::query()->whereKey($items->pluck('order.i_supplier_id')->filter()->unique()->all())
            ->pluck('supplier_name', 'id_supplier')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'doc_no', 'label' => 'PO No'],
            ['key' => 'doc_date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'delivery', 'label' => 'Delivery Date', 'type' => 'date'],
            ['key' => 'uom', 'label' => 'UoM'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'sum' => true],
            ['key' => 'received', 'label' => 'Received', 'type' => 'number', 'sum' => true],
            ['key' => 'remain', 'label' => 'Remaining', 'type' => 'number', 'sum' => true],
            ['key' => 'value', 'label' => 'Outstanding Value', 'type' => 'money', 'sum' => true],
            ['key' => 'late', 'label' => 'Late', 'type' => 'badge', 'tones' => ['Late' => 'danger']],
            ['key' => 'status', 'label' => 'PO Status', 'type' => 'badge', 'tones' => ['SENT' => 'info', 'PARTIALLY_RECEIVED' => 'warn']],
        ];
    }

    protected function row(Model $m): array
    {
        $po = $m->order;
        $qty = (float) $m->i_qty;
        $remain = $qty - (float) ($m->c_qty_received ?? 0);
        $delivery = $po?->i_delivery_date ? substr((string) $po->i_delivery_date, 0, 10) : null;

        return [
            'doc_no' => $po?->doc_no, 'doc_date' => $po?->i_doc_date ? substr((string) $po->i_doc_date, 0, 10) : null,
            'supplier' => $this->suppliers[$po?->i_supplier_id] ?? null, 'item' => $m->i_description, 'item_code' => $m->c_item_code,
            'delivery' => $delivery, 'uom' => $m->i_uom, 'qty' => $qty, 'received' => (float) ($m->c_qty_received ?? 0), 'remain' => $remain,
            'value' => $qty > 0 ? $remain / $qty * (float) ($m->c_line_net ?? 0) : 0,
            'late' => $delivery && $delivery < now()->toDateString() ? 'Late' : null, 'status' => $po?->status,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Waiting', 'where' => ['status' => 'SENT'], 'tone' => 'info'],
            ['label' => 'Partially received', 'where' => ['status' => 'PARTIALLY_RECEIVED'], 'tone' => 'warn'],
            ['label' => 'Late', 'where' => ['late' => 'Late'], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['supplier' => 'Supplier', 'late' => 'Late', 'status' => 'PO Status'];
    }

    public function groups(): array
    {
        return ['doc_no' => 'PO No', 'supplier' => 'Supplier'];
    }
}
