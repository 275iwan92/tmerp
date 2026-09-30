<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Sdi\CoOrderDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Satu baris = satu jadwal kirim CPDEL (co_order_delivery) beserta item CO-nya.
class CoShipDetailReport extends CoReportGrid
{
    public function slug(): string
    {
        return 'co-rpt-ship-det';
    }

    public function title(): string
    {
        return 'Order Qty Only - Detail with Shipment';
    }

    protected function query(): Builder
    {
        return CoOrderDelivery::query()->with(['item.order.customer', 'item.order.salesPerson', 'item.product'])
            ->whereHas('order', fn ($o) => $o->where('stat_cancel', false))->orderByDesc('order_id')->orderBy('ship_date');
    }

    protected function row(Model $m): array
    {
        return ['ship_date' => $m->ship_date ? substr((string) $m->ship_date, 0, 10) : null, 'ship_qty' => (float) $m->ship_qty,
            'ship_type' => $m->shipment_type, 'ship_status' => $m->status] + ($m->item ? $this->itemFields($m->item) : []);
    }

    public function columns(): array
    {
        return [
            ['key' => 'co', 'label' => 'No CO'],
            ['key' => 'co_date', 'label' => 'Tgl CO', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'po', 'label' => 'No PO Customer'],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty Order', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Satuan'],
            ['key' => 'ship_date', 'label' => 'Tgl Kirim', 'type' => 'date'],
            ['key' => 'ship_qty', 'label' => 'Qty Kirim', 'type' => 'number', 'sum' => true],
            ['key' => 'ship_type', 'label' => 'Jenis Kirim'],
            ['key' => 'ship_status', 'label' => 'Status Kirim', 'type' => 'badge', 'tones' => ['PLANNED' => 'warn', 'SHIPPED' => 'success']],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
        ];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'ship_status' => 'Status Kirim', 'ship_type' => 'Jenis Kirim'];
    }
}
