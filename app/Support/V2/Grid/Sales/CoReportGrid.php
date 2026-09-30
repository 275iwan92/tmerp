<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Master\CustCustomer;
use App\Models\Master\ProdProduct;
use App\Models\Master\SalesPerson;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoLine;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Laporan Customer Order (pengganti menu v1 "Cust.Order Reports", ScriptCase: sdi_co + sdi_co_detail).
// Satu baris = satu item CO. Turunan cukup memilih cakupan (scope), kolom, dan grup.
// Kolom v1 yang BELUM ada padanannya di tabel v2 (shipment_method, cfg_color, order_type, qty_minim) sengaja tidak
// ditampilkan — menunggu keputusan skema, tidak dikarang.
abstract class CoReportGrid extends DbGrid
{
    public const STATUS_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'info', 'CONFIRMED' => 'success', 'CLOSED' => 'accent', 'CANCELLED' => 'danger'];

    protected array $planned = [];

    protected array $shipped = [];

    public function module(): string
    {
        return 'SALES';
    }

    public function moduleLabel(): string
    {
        return 'Sales';
    }

    public function group(): string
    {
        return 'Sales Order › Report';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    // Harga hanya untuk Sales, Costing, Operational Manager ke atas (SecUser::canViewSalesPrice).
    public const PRICE_KEYS = ['currency', 'price', 'disc', 'subtotal', 'value'];

    protected function restrictedKeys(): array
    {
        return auth()->user()?->canViewSalesPrice() ? [] : self::PRICE_KEYS;
    }

    protected function seesPrice(): bool
    {
        return $this->restrictedKeys() === [];
    }

    public function icon(): string
    {
        return 'fa-solid fa-chart-line';
    }

    public function search(): string
    {
        return 'Cari no. CO, customer, no. PO, product...';
    }

    protected function models(): array
    {
        return [CoOrder::class, CoOrderItem::class, CustCustomer::class, SalesPerson::class, ProdProduct::class, CoOrderDelivery::class, DoLine::class];
    }

    // Cakupan baris per laporan (dipanggil pada query item CO).
    protected function scope(Builder $q): Builder
    {
        return $q;
    }

    protected function query(): Builder
    {
        return $this->scope(CoOrderItem::query()->with(['order.customer', 'order.salesPerson', 'product']))
            ->orderByDesc('order_id')->orderBy('line_seq');
    }

    // Rencana kirim (CPDEL) & terkirim (DO SHIPPED) per item — dua query agregat, dicocokkan di PHP.
    protected function prefetch(Collection $items): void
    {
        $ids = $items->map(fn ($m) => $m instanceof CoOrderItem ? $m->getKey() : $m->item_id)->unique()->values();
        $this->planned = CoOrderDelivery::query()->whereIn('item_id', $ids)->groupBy('item_id')
            ->selectRaw('item_id, SUM(ship_qty) q')->pluck('q', 'item_id')->all();
        $this->shipped = DoLine::query()->whereIn('co_order_item_id', $ids)
            ->whereHas('delivery', fn ($q) => $q->where('status', 'SHIPPED'))->groupBy('co_order_item_id')
            ->selectRaw('co_order_item_id, SUM(qty_do) q')->pluck('q', 'co_order_item_id')->all();
    }

    protected function status(CoOrder $o): string
    {
        return $o->stat_cancel ? 'CANCELLED' : (string) $o->doc_status;
    }

    // Semua nilai satu item CO; turunan memilih kolom mana yang tampil.
    protected function itemFields(CoOrderItem $i): array
    {
        $o = $i->order;
        $shipped = (float) ($this->shipped[$i->getKey()] ?? 0);

        return [
            'co' => $o?->co_no, 'co_date' => $o?->co_date ? substr((string) $o->co_date, 0, 10) : null,
            'month' => $o?->co_date ? substr((string) $o->co_date, 0, 7) : null,
            'customer' => $o?->customer?->customer_name, 'po' => $o?->customer_po_no,
            'confirm' => $o && $o->doc_status === 'CONFIRMED' ? 'Yes' : 'No', 'status' => $o ? $this->status($o) : null,
            'mode' => $o?->entry_mode, 'note' => $o?->notes, 'sales' => $o?->salesPerson?->sales_name,
            'product' => $i->product ? trim($i->product->product_name.($i->product->design_no ? ' ('.$i->product->design_no.')' : '')) : $i->description,
            'qty' => (float) $i->qty, 'unit' => $i->qty_unit, 'qty_min' => $i->qty_min !== null ? (float) $i->qty_min : null,
            'qty_max' => $i->qty_max !== null ? (float) $i->qty_max : null, 'due' => $i->due_date ? substr((string) $i->due_date, 0, 10) : null,
            'currency' => $o?->currency_code, 'price' => (float) $i->unit_price, 'disc' => (float) $i->discount_pct, 'subtotal' => (float) $i->subtotal,
            'planned' => (float) ($this->planned[$i->getKey()] ?? 0), 'shipped' => $shipped, 'remain' => max(0, (float) $i->qty - $shipped),
            // Penanda kotak statistik (pengganti laporan v1 terpisah "Status Open and Not Cancel" & "Outstanding Order").
            'open' => $o && ! $o->stat_cancel && ! in_array($o->doc_status, ['CLOSED', 'CANCELLED'], true),
            'outstanding' => $o && ! $o->stat_cancel && $o->doc_status === 'CONFIRMED' && (float) $i->qty > $shipped,
        ];
    }

    protected function row(Model $m): array
    {
        return $this->itemFields($m);
    }

    // Kolom baku laporan "Order Qty Only" (urutan mengikuti laporan v1).
    protected function qtyColumns(): array
    {
        return [
            ['key' => 'co', 'label' => 'No CO'],
            ['key' => 'co_date', 'label' => 'Tgl CO', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'po', 'label' => 'No PO Customer'],
            ['key' => 'confirm', 'label' => 'Confirm', 'type' => 'badge', 'tones' => ['Yes' => 'success', 'No' => 'neutral']],
            ['key' => 'note', 'label' => 'Note'],
            ['key' => 'sales', 'label' => 'Sales By'],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'sum' => true],
            ['key' => 'unit', 'label' => 'Satuan'],
            ['key' => 'qty_min', 'label' => 'Qty Limit Min', 'type' => 'number'],
            ['key' => 'qty_max', 'label' => 'Qty Limit Max', 'type' => 'number'],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
        ];
    }

    public function columns(): array
    {
        return $this->qtyColumns();
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'sales' => 'Sales By', 'confirm' => 'Confirm', 'status' => 'Status', 'month' => 'Bulan'];
    }

    public function groups(): array
    {
        return ['customer' => 'Customer', 'status' => 'Status', 'sales' => 'Sales By', 'month' => 'Bulan', 'co' => 'No CO'];
    }
}
