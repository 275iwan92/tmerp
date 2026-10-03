<?php

namespace App\Support\V2\Grid\Pur;

use App\Models\Master\SupSupplier;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Support\V2\Data\PurchaseOrder;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Daftar Purchase Order (Grid Transactional) di layar v2 purchase-order: Open / + New Record = form di layar yang sama.
class PurchaseOrderGrid extends DbGrid
{
    private array $suppliers = [];

    public function slug(): string
    {
        return 'purchase-order';
    }

    public function title(): string
    {
        return 'Purchase Order (PO)';
    }

    public function module(): string
    {
        return 'PROC';
    }

    public function moduleLabel(): string
    {
        return 'Procurement';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Order';
    }

    public function icon(): string
    {
        return 'fa-solid fa-file-invoice-dollar';
    }

    public function openUrl(): ?string
    {
        return $this->url().'?id={id}';
    }

    public function newUrl(): ?string
    {
        return $this->url().'?new=1';
    }

    protected function models(): array
    {
        return [PoOrder::class, PoLine::class, SupSupplier::class];
    }

    public function search(): string
    {
        return 'Search PO no., supplier...';
    }

    protected function query(): Builder
    {
        return PoOrder::query()->with('lines')->orderByDesc('po_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->suppliers = SupSupplier::query()->whereKey($items->pluck('i_supplier_id')->filter()->unique()->all())->pluck('supplier_name', 'id_supplier')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'PO No. / Supplier', 'type' => 'doc', 'party' => 'supplier'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'delivery', 'label' => 'Ship Date', 'type' => 'date'],
            ['key' => 'total', 'label' => 'Grand Total', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => PurchaseOrder::STATUS_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'no' => $m->doc_no ?: '(draft #'.$m->po_id.')', 'supplier' => $this->suppliers[$m->i_supplier_id] ?? '—',
            'date' => $m->i_doc_date?->toDateString(), 'delivery' => $m->i_delivery_date?->toDateString(),
            'total' => (float) $m->c_grand_total, 'status' => $m->status, 'items' => PurchaseOrder::lineRows($m),
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'received', 'label' => 'Received', 'type' => 'number'],
            ['key' => 'outstanding', 'label' => 'Remaining', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Line Status'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => 'Waiting for approval', 'where' => ['status' => 'SUBMITTED'], 'tone' => 'warn'],
            ['label' => 'Approved / Sent', 'where' => ['status' => ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED']], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['supplier' => 'Supplier', 'status' => 'Status'];
    }
}
