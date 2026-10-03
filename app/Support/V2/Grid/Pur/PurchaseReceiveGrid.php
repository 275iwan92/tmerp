<?php

namespace App\Support\V2\Grid\Pur;

use App\Models\Master\CustCustomer;
use App\Models\Master\SupSupplier;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Support\V2\Data\PurchaseReceive;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Daftar Goods Receipt / SPB (Grid Transactional) di layar v2 purchase-receive: tiga jenis penerimaan (PO, bahan customer,
// subcon) dalam satu grid; pihak = supplier, atau customer untuk penerimaan bahan customer.
class PurchaseReceiveGrid extends DbGrid
{
    private array $suppliers = [];

    private array $customers = [];

    public function slug(): string
    {
        return 'purchase-receive';
    }

    public function title(): string
    {
        return 'Goods Receipt (SPB)';
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
        return 'Receive';
    }

    public function icon(): string
    {
        return 'fa-solid fa-truck-ramp-box';
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
        return [PvReceipt::class, PvLine::class];
    }

    public function search(): string
    {
        return 'Search receipt no., supplier, PO no....';
    }

    protected function query(): Builder
    {
        return PvReceipt::query()->with('lines')->orderByDesc('pv_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->suppliers = SupSupplier::query()->whereKey($items->pluck('i_supplier_id')->filter()->unique()->all())->pluck('supplier_name', 'id_supplier')->all();
        $this->customers = CustCustomer::query()->whereKey($items->pluck('i_customer_id')->filter()->unique()->all())->pluck('customer_name', 'id_customer')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Receipt No. / Supplier', 'type' => 'doc', 'party' => 'supplier'],
            ['key' => 'type', 'label' => 'Receipt Type', 'type' => 'badge', 'tones' => PurchaseReceive::TYPE_TONES],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'warehouse', 'label' => 'Warehouse'],
            ['key' => 'po', 'label' => 'PO No.'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => PurchaseReceive::STATUS_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'no' => $m->doc_no ?: 'DRAFT-'.$m->pv_id,
            'supplier' => $m->i_receipt_type === 'CUSTOMER' ? ($this->customers[$m->i_customer_id] ?? '—') : ($this->suppliers[$m->i_supplier_id] ?? '—'),
            'type' => PurchaseReceive::TYPES[$m->i_receipt_type] ?? $m->i_receipt_type, 'date' => $m->i_doc_date?->toDateString(),
            'warehouse' => $m->i_warehouse_code ?: '—', 'po' => $m->c_po_doc_list, 'status' => $m->status,
            'items' => $m->lines->map(fn (PvLine $l) => ['code' => $l->c_item_code, 'name' => $l->i_description, 'po' => $l->c_po_doc_no, 'qty' => (float) $l->i_qty_purchase,
                'unit' => $l->i_uom_purchase, 'accepted' => (float) $l->c_qty_accepted, 'rejected' => (float) $l->c_qty_rejected])->all(),
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'po', 'label' => 'PO No.'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'accepted', 'label' => 'Accepted', 'type' => 'number'],
            ['key' => 'rejected', 'label' => 'Rejected', 'type' => 'number'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Draft / QC', 'where' => ['status' => ['DRAFT', 'QC_PENDING']], 'tone' => 'warn'],
            ['label' => 'Posted', 'where' => ['status' => 'POSTED'], 'tone' => 'success'],
            ['label' => 'From Customer', 'where' => ['type' => 'From Customer'], 'tone' => 'info'],
            ['label' => 'From Subcon', 'where' => ['type' => 'From Subcon'], 'tone' => 'warn'],
        ];
    }

    public function filters(): array
    {
        return ['supplier' => 'Supplier', 'warehouse' => 'Warehouse', 'status' => 'Status'];
    }
}
