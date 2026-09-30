<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Sdi\CoOrderDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Customer Order Shipment Detail (v1: Order Qty Only - Detail with Shipment): satu baris = satu jadwal kirim CPDEL.
class CoShipDetailReport extends CoReportGrid
{
    public function slug(): string
    {
        return 'co-rpt-ship-det';
    }

    public function title(): string
    {
        return 'Customer Order Shipment Detail';
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

    public function guide(): ?string
    {
        return 'Satu baris = satu jadwal kirim. Klik tombol Kolom untuk menambah Sales By, Due Date, Status CO'.($this->seesPrice() ? ', Harga' : '').', Terkirim, Sisa; Grup per Customer / Tgl Kirim / No CO menjumlahkan Qty Kirim.';
    }

    public function columns(): array
    {
        return [
            ['key' => 'co', 'label' => 'No CO'],
            ['key' => 'co_date', 'label' => 'Tgl CO', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'po', 'label' => 'No PO Customer'],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'order_type', 'label' => 'Order Type', 'type' => 'badge', 'tones' => ['NEW' => 'info', 'RTP' => 'success', 'RDP' => 'warn'], 'visible' => false],
            ['key' => 'cfg_color', 'label' => 'Cfg Color', 'visible' => false],
            ['key' => 'qty', 'label' => 'Qty Order', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Satuan'],
            ['key' => 'ship_date', 'label' => 'Tgl Kirim', 'type' => 'date'],
            ['key' => 'ship_qty', 'label' => 'Qty Kirim', 'type' => 'number', 'sum' => true],
            ['key' => 'ship_type', 'label' => 'Jenis Kirim'],
            ['key' => 'ship_method', 'label' => 'Shipment Method'],
            ['key' => 'recipient', 'label' => 'Penerima', 'visible' => false],
            ['key' => 'shipper', 'label' => 'Shipper', 'visible' => false],
            ['key' => 'forwarder', 'label' => 'Forwarder', 'visible' => false],
            ['key' => 'ship_status', 'label' => 'Status Kirim', 'type' => 'badge', 'tones' => ['PLANNED' => 'warn', 'SHIPPED' => 'success']],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date', 'visible' => false],
            ['key' => 'sales', 'label' => 'Sales By', 'visible' => false],
            ['key' => 'status', 'label' => 'Status CO', 'type' => 'badge', 'tones' => self::STATUS_TONES, 'visible' => false],
            ['key' => 'price', 'label' => 'Harga Satuan', 'type' => 'money', 'visible' => false],
            ['key' => 'shipped', 'label' => 'Terkirim (item)', 'type' => 'number', 'visible' => false],
            ['key' => 'remain', 'label' => 'Sisa (item)', 'type' => 'number', 'visible' => false],
        ];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'ship_status' => 'Status Kirim', 'ship_type' => 'Jenis Kirim'];
    }

    public function groups(): array
    {
        return ['customer' => 'Customer', 'ship_date' => 'Tgl Kirim', 'co' => 'No CO', 'ship_status' => 'Status Kirim'];
    }
}
