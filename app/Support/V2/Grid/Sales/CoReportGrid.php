<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Master\CustCustomer;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\SalesPerson;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoLine;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;

// Laporan Customer Order (pengganti menu v1 "Cust.Order Reports", ScriptCase: sdi_co + sdi_co_detail).
// Satu baris = satu item CO. Turunan cukup memilih cakupan (scope), kolom, dan grup.
// Order Type & Cfg Color v1 diturunkan dari data v2 (bukan kolom baru): jenis inquiry item CO (atau inquiry asal
// Master Product untuk CO INSTANT) & cfg_color part-nya. Shipment Method, Penerima/Shipper/Forwarder & Qty Minim
// = kolom v2 baru (migration 2026_09_30_000011).
abstract class CoReportGrid extends DbGrid
{
    public const STATUS_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'info', 'CONFIRMED' => 'success', 'CLOSED' => 'accent', 'CANCELLED' => 'danger'];

    protected array $planned = [];

    protected array $shipped = [];

    // Kode v1 (user 2026-09-30): RTP = Repeat Tanpa Perubahan (= REKALKULASI), RDP = Repeat Dengan Perubahan (= REVISI).
    public const ORDER_TYPES = ['NEW' => 'NEW', 'RECALC' => 'RTP', 'REVISE' => 'RDP'];

    protected array $orderType = [];

    protected array $cfgColor = [];

    protected array $parties = [];

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
        return 'Search CO no., customer, PO no., product...';
    }

    protected function models(): array
    {
        return [CoOrder::class, CoOrderItem::class, CustCustomer::class, SalesPerson::class, ProdProduct::class, CoOrderDelivery::class, DoLine::class,
            IqpInquiry::class, IqpPart::class, ProdPart::class];
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
        $this->loadSpec($items->map(fn ($m) => $m instanceof CoOrderItem ? $m : $m->item)->filter());
        $this->loadParties($items->map(fn ($m) => $m instanceof CoOrderItem ? $m->order : $m->item?->order)->filter());
    }

    // Nama Penerima / Shipper / Forwarder (customer, tm2mst) untuk CO di halaman ini — satu query lintas-DB.
    protected function loadParties(BaseCollection $orders): void
    {
        $ids = $orders->flatMap(fn (CoOrder $o) => [$o->recipient_customer_id, $o->shipper_customer_id, $o->forwarder_customer_id])->filter()->unique();
        $this->parties = $ids->isEmpty() ? [] : CustCustomer::query()->whereIn('id_customer', $ids)->pluck('customer_name', 'id_customer')->all();
    }

    // Kolom pengiriman level CO (dipakai laporan per item & per CO).
    protected function shipmentFields(?CoOrder $o): array
    {
        return [
            'ship_method' => $o?->shipment_method, 'recipient' => $this->parties[$o?->recipient_customer_id] ?? null,
            'shipper' => $this->parties[$o?->shipper_customer_id] ?? null, 'forwarder' => $this->parties[$o?->forwarder_customer_id] ?? null,
        ];
    }

    // Order Type & Cfg Color per item CO: 3 query ringkas (inquiry, part inquiry, part master), dicocokkan di PHP.
    protected function loadSpec(BaseCollection $items): void
    {
        $inq = $items->mapWithKeys(fn (CoOrderItem $i) => [$i->getKey() => $i->inquiry_id ?: $i->product?->source_inquiry_id]);
        $types = IqpInquiry::query()->whereIn('id_inquiry', $inq->filter()->unique())->pluck('inquiry_type', 'id_inquiry');
        $colorsByInq = IqpPart::query()->whereIn('inquiry_id', $inq->filter()->unique())->whereNotNull('cfg_color')->where('cfg_color', '<>', '')
            ->orderBy('part_seq')->get(['inquiry_id', 'cfg_color'])->groupBy('inquiry_id')->map(fn ($p) => $p->pluck('cfg_color')->unique()->implode(', '));
        $colorsByProduct = ProdPart::query()->whereIn('product_id', $items->pluck('product_id')->filter()->unique())->whereNotNull('cfg_color')
            ->where('cfg_color', '<>', '')->get(['product_id', 'cfg_color'])->groupBy('product_id')->map(fn ($p) => $p->pluck('cfg_color')->unique()->implode(', '));
        foreach ($items as $i) {
            $q = $inq[$i->getKey()];
            $this->orderType[$i->getKey()] = self::ORDER_TYPES[$types[$q] ?? ''] ?? null;
            $this->cfgColor[$i->getKey()] = ($q ? ($colorsByInq[$q] ?? null) : null) ?? ($colorsByProduct[$i->product_id] ?? null);
        }
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
            'order_type' => $this->orderType[$i->getKey()] ?? null, 'cfg_color' => $this->cfgColor[$i->getKey()] ?? null,
            'qty_minim' => $i->qty_minim ? 'Yes' : 'No',
            'qty' => (float) $i->qty, 'unit' => $i->qty_unit, 'qty_min' => $i->qty_min !== null ? (float) $i->qty_min : null,
            'qty_max' => $i->qty_max !== null ? (float) $i->qty_max : null, 'due' => $i->due_date ? substr((string) $i->due_date, 0, 10) : null,
            'currency' => $o?->currency_code, 'price' => (float) $i->unit_price, 'disc' => (float) $i->discount_pct, 'subtotal' => (float) $i->subtotal,
            'planned' => (float) ($this->planned[$i->getKey()] ?? 0), 'shipped' => $shipped, 'remain' => max(0, (float) $i->qty - $shipped),
            // Penanda kotak statistik (pengganti laporan v1 terpisah "Status Open and Not Cancel" & "Outstanding Order").
            'open' => $o && ! $o->stat_cancel && ! in_array($o->doc_status, ['CLOSED', 'CANCELLED'], true),
            'outstanding' => $o && ! $o->stat_cancel && $o->doc_status === 'CONFIRMED' && (float) $i->qty > $shipped,
        ] + $this->shipmentFields($o);
    }

    protected function row(Model $m): array
    {
        return $this->itemFields($m);
    }

    // Kolom baku laporan "Order Qty Only" (urutan mengikuti laporan v1).
    protected function qtyColumns(): array
    {
        return [
            ['key' => 'co', 'label' => 'CO No.'],
            ['key' => 'co_date', 'label' => 'CO Date', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'po', 'label' => 'Customer PO No.'],
            ['key' => 'ship_method', 'label' => 'Shipment Method'],
            ['key' => 'confirm', 'label' => 'Confirm', 'type' => 'badge', 'tones' => ['Yes' => 'success', 'No' => 'neutral']],
            ['key' => 'note', 'label' => 'Note'],
            ['key' => 'sales', 'label' => 'Sales By'],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'sum' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'order_type', 'label' => 'Order Type', 'type' => 'badge', 'tones' => ['NEW' => 'info', 'RTP' => 'success', 'RDP' => 'warn']],
            ['key' => 'cfg_color', 'label' => 'Cfg Color'],
            ['key' => 'qty_minim', 'label' => 'Qty Minim', 'type' => 'badge', 'tones' => ['Yes' => 'warn', 'No' => 'neutral']],
            ['key' => 'qty_min', 'label' => 'Qty Limit Min', 'type' => 'number'],
            ['key' => 'qty_max', 'label' => 'Qty Limit Max', 'type' => 'number'],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'recipient', 'label' => 'Receiver', 'visible' => false],
            ['key' => 'shipper', 'label' => 'Shipper', 'visible' => false],
            ['key' => 'forwarder', 'label' => 'Forwarder', 'visible' => false],
        ];
    }

    public function columns(): array
    {
        return $this->qtyColumns();
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'sales' => 'Sales By', 'order_type' => 'Order Type', 'ship_method' => 'Shipment Method', 'qty_minim' => 'Qty Minim', 'confirm' => 'Confirm', 'status' => 'Status', 'month' => 'Month'];
    }

    public function groups(): array
    {
        return ['customer' => 'Customer', 'status' => 'Status', 'order_type' => 'Order Type', 'sales' => 'Sales By', 'month' => 'Month', 'co' => 'CO No.'];
    }
}
