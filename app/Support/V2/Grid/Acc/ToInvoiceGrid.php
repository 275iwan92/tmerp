<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use App\Services\Acc\CustomerInvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// To Invoice (user 2026-10-02: tiap tagihan harus berasal dari Surat Jalan yang divalidasi customer): order yang Surat
// Jalannya belum habis ditagih. Ready = customer sudah menerima (Proof of Delivery) -> buka baris = pilih baris Surat Jalan
// untuk Draft invoice. Waiting = sudah dikirim, bukti terimanya belum dicatat (belum bisa ditagih).
class ToInvoiceGrid extends AccGrid
{
    private const STATE_TONES = ['Ready to invoice' => 'success', 'Waiting proof of delivery' => 'warn'];

    private array $customers = [];

    private \Illuminate\Support\Collection $chains;

    public function slug(): string
    {
        return 'acc-to-invoice';
    }

    public function title(): string
    {
        return 'To Invoice';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Transaction Flows';
    }

    public function icon(): string
    {
        return 'fa-solid fa-truck-ramp-box';
    }

    public function guide(): ?string
    {
        return 'Orders with delivery notes not fully invoiced. Ready = the customer has received the goods (Proof of Delivery); open the row to make the invoice. '
            .'Waiting = shipped, the signed delivery note is not recorded yet.';
    }

    protected function models(): array
    {
        return [CoOrder::class, DoLine::class, DoDelivery::class, CustCustomer::class];
    }

    public function search(): string
    {
        return 'Search order no., customer, delivery note...';
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'customer-invoice').'?new=1&type=INVOICE&co_id={id}';
    }

    protected function query(): Builder
    {
        $open = DoLine::query()->whereNotNull('co_order_id')->where(fn ($q) => $q->whereNull('qty_received')->orWhereColumn('qty_received', '>', 'qty_invoiced'))
            ->whereHas('delivery', fn ($d) => $d->where('do_type', '<>', 'SUBCON')->whereNotIn('status', ['DRAFT', 'CANCELLED']))->distinct()->pluck('co_order_id');

        return CoOrder::query()->whereKey($open->all())->orderByDesc('id_order');
    }

    protected function prefetch(Collection $items): void
    {
        $this->customers = CustCustomer::withoutGlobalScopes()->whereIn('id_customer', $items->pluck('customer_id')->unique())->pluck('customer_name', 'id_customer')->all();
        $svc = app(CustomerInvoiceService::class);
        $this->chains = $items->mapWithKeys(fn (CoOrder $o) => [$o->id_order => $svc->deliveryChain($o->id_order)->where('state', '<>', 'Fully invoiced')->values()]);
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'CO No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'po', 'label' => 'Customer PO No.'],
            ['key' => 'tax', 'label' => 'Tax Identity'],
            ['key' => 'oldest', 'label' => 'Oldest Delivery', 'type' => 'date'],
            ['key' => 'ready', 'label' => 'Ready to Invoice (value)', 'type' => 'money', 'sum' => true],
            ['key' => 'waiting', 'label' => 'Waiting Proof of Delivery (value)', 'type' => 'money', 'sum' => true],
            ['key' => 'state', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATE_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        $svc = app(CustomerInvoiceService::class);
        $chain = $this->chains[$m->id_order] ?? collect();
        $ready = $chain->where('state', '<>', 'Waiting proof of delivery');
        $waiting = $chain->where('state', 'Waiting proof of delivery');

        return [
            'no' => $m->co_no, 'customer' => $this->customers[$m->customer_id] ?? '—', 'po' => $m->customer_po_no, 'tax' => $m->is_ppn ? 'PPN' : 'Non PPN',
            'oldest' => $chain->min(fn (array $r) => $r['line']->delivery->do_date?->toDateString()),
            'ready' => round($ready->sum(fn (array $r) => $svc->goodsValue($r['line'], $svc->remaining($r['line']))), 2),
            'waiting' => round($waiting->sum(fn (array $r) => $svc->goodsValue($r['line'], (float) $r['line']->qty_do)), 2),
            'state' => $ready->isNotEmpty() ? 'Ready to invoice' : 'Waiting proof of delivery',
            'items' => $chain->map(fn (array $r) => ['product' => $r['line']->item_description, 'dn' => $r['line']->delivery->do_no, 'shipped' => (float) $r['line']->qty_do,
                'received' => $r['line']->qty_received === null ? null : (float) $r['line']->qty_received, 'invoiced' => (float) $r['line']->qty_invoiced,
                'unit' => $r['line']->qty_unit, 'state' => $r['state']])->all(),
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'dn', 'label' => 'Delivery Note'],
            ['key' => 'shipped', 'label' => 'Shipped', 'type' => 'number'],
            ['key' => 'received', 'label' => 'Received', 'type' => 'number'],
            ['key' => 'invoiced', 'label' => 'Qty Invoiced', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'state', 'label' => 'Status'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Ready to invoice', 'where' => ['state' => 'Ready to invoice'], 'tone' => 'success'],
            ['label' => 'Waiting proof of delivery', 'where' => ['state' => 'Waiting proof of delivery'], 'tone' => 'warn'],
        ];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'tax' => 'Tax Identity', 'state' => 'Status'];
    }
}
