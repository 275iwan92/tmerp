<?php

namespace App\Support\V2\Grid\Pur;

use App\Models\Master\SupSupplier;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrtLine;
use App\Models\Pur\PrtReturn;
use App\Models\Pur\PvReceipt;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Purchase Return / Nota Retur Barang (v1: pages::pur.prt.index -> entry). Buka = form v1; + New Record = pemilih sumber v1
// (dari SPB yang punya qty ditolak QC, atau dari stok per supplier).
class PurchaseReturnGrid extends DbGrid
{
    private const STATUS_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'CONFIRMED' => 'info', 'COMPLETED' => 'success', 'CANCELLED' => 'danger'];

    private const SOURCES = ['QC_REJECT' => 'QC reject', 'FROM_STOCK' => 'From stock'];

    private const DISPOSITIONS = ['CREDIT_NOTE' => 'Credit note', 'REPLACEMENT' => 'Replacement'];

    private array $suppliers = [];

    private array $receipts = [];

    private array $orders = [];

    public function slug(): string
    {
        return 'purchase-return';
    }

    public function title(): string
    {
        return 'Purchase Return';
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
        return 'Return';
    }

    public function icon(): string
    {
        return 'fa-solid fa-reply';
    }

    public function openUrl(): ?string
    {
        return $this->routeUrl('pur.prt.entry', 'prt');
    }

    public function newUrl(): ?string
    {
        return route('pur.prt.list').'?new=1';
    }

    protected function models(): array
    {
        return [PrtReturn::class, PrtLine::class, SupSupplier::class];
    }

    public function search(): string
    {
        return 'Search return no, supplier, reason...';
    }

    protected function query(): Builder
    {
        return PrtReturn::query()->with('lines')->orderByDesc('prt_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->suppliers = SupSupplier::query()->whereKey($items->pluck('i_supplier_id')->filter()->unique()->all())->pluck('supplier_name', 'id_supplier')->all();
        $this->receipts = PvReceipt::query()->whereKey($items->pluck('pv_id')->filter()->unique()->all())->pluck('doc_no', 'pv_id')->all();
        $this->orders = PoOrder::query()->whereKey($items->pluck('po_id')->filter()->unique()->all())->pluck('doc_no', 'po_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'doc_no', 'label' => 'Return No. / Supplier', 'type' => 'doc', 'party' => 'supplier'],
            ['key' => 'doc_date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'disposition', 'label' => 'Settlement'],
            ['key' => 'pv_no', 'label' => 'Goods Receipt'],
            ['key' => 'po_no', 'label' => 'Purchase Order', 'visible' => false],
            ['key' => 'reason', 'label' => 'Reason'],
            ['key' => 'credit', 'label' => 'Credit Value', 'type' => 'money'],
            ['key' => 'returned', 'label' => 'Returned On', 'type' => 'date', 'visible' => false],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            // Nomor baru terbit saat Confirm; draft ditandai dengan id-nya supaya persegi dokumen tidak kosong.
            'doc_no' => $m->doc_no ?: 'DRAFT-'.$m->prt_id, 'supplier' => $this->suppliers[$m->i_supplier_id] ?? null,
            'doc_date' => $m->i_doc_date?->toDateString(), 'source' => self::SOURCES[$m->i_source] ?? $m->i_source,
            'disposition' => self::DISPOSITIONS[$m->i_disposition] ?? $m->i_disposition, 'pv_no' => $this->receipts[$m->pv_id] ?? null,
            'po_no' => $this->orders[$m->po_id] ?? null, 'reason' => $m->i_reason, 'credit' => (float) $m->c_credit_value,
            'returned' => $m->physical_return_date?->toDateString(), 'status' => $m->status ?: 'DRAFT',
            'items' => $m->lines->map(fn (PrtLine $l) => ['code' => $l->c_item_code, 'name' => $l->i_description, 'qty' => (float) $l->i_qty,
                'unit' => $l->i_uom, 'lot' => $l->i_lot_no, 'replacement' => $l->replacement_status])->all(),
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'lot', 'label' => 'Lot No.'],
            ['key' => 'replacement', 'label' => 'Replacement'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => 'Waiting', 'where' => ['status' => 'SUBMITTED'], 'tone' => 'warn'],
            ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'info'],
            ['label' => 'Completed', 'where' => ['status' => 'COMPLETED'], 'tone' => 'success'],
            ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['source' => 'Source', 'disposition' => 'Settlement', 'status' => 'Status'];
    }
}
