<?php

namespace Tests\Feature;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderExternalCost;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sys\SecUser;
use App\Services\Sales\CoEntryService as Svc;
use App\Support\V2\Data\CustomerOrder as CoData;
use Tests\TestCase;

// Aturan entri Customer Order (dipindah dari komponen v1 pages::co.entry). Menulis ke tm2sdi.co_order(+item/delivery/external_cost);
// CO buatan test dihapus di tearDown. Aturan inti: hanya DRAFT yang bisa diubah, baris snapshot Quotation hanya boleh ubah due date /
// due note / Qty Minim dan tidak bisa dihapus.
class CoEntryServiceTest extends TestCase
{
    private ?CoOrder $o = null;

    private int $snapshot;

    private int $manual;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $this->o = CoOrder::create([
            'co_no' => 'CO-TEST-ENTRY', 'co_date' => '2026-10-01', 'customer_id' => CustCustomer::query()->where('activate', true)->value('id_customer'),
            'entry_mode' => 'INSTANT', 'doc_status' => 'DRAFT', 'created_by' => 'test', 'updated_by' => 'test',
        ]);
        $line = ['order_id' => $this->o->id_order, 'line_kind' => 'PRODUCT', 'qty' => 10, 'qty_unit' => 'Pcs', 'unit_price' => 1000, 'discount_pct' => 0, 'subtotal' => 10000, 'created_by' => 'test', 'updated_by' => 'test'];
        // quotation_item_id bukan null = baris snapshot Quotation.
        $this->snapshot = CoOrderItem::create($line + ['line_seq' => 1, 'description' => 'Snapshot', 'quotation_item_id' => 999999999])->id_item;
        $this->manual = CoOrderItem::create($line + ['line_seq' => 2, 'description' => 'Manual'])->id_item;
    }

    protected function tearDown(): void
    {
        if ($this->o) {
            CoOrderDelivery::where('order_id', $this->o->id_order)->delete();
            CoOrderExternalCost::where('order_id', $this->o->id_order)->delete();
            CoOrderItem::where('order_id', $this->o->id_order)->delete();
            CoOrder::whereKey($this->o->id_order)->delete();
        }
        parent::tearDown();
    }

    public function test_header_only_allowed_fields_and_only_while_draft(): void
    {
        Svc::updateHeader($this->o, 'customer_po_no', 'PO-TEST');
        Svc::updateHeader($this->o, 'doc_status', 'CONFIRMED');
        $this->assertSame('PO-TEST', CoOrder::find($this->o->id_order)->customer_po_no);
        $this->assertSame('DRAFT', CoOrder::find($this->o->id_order)->doc_status);

        CoOrder::whereKey($this->o->id_order)->update(['doc_status' => 'CONFIRMED']);
        Svc::updateHeader($this->o->refresh(), 'customer_po_no', 'PO-LATE');
        $this->assertSame('PO-TEST', CoOrder::find($this->o->id_order)->customer_po_no, 'Confirmed CO is locked.');
    }

    public function test_snapshot_line_is_locked_except_due_date_note_and_qty_minim(): void
    {
        Svc::updateItem($this->o, $this->snapshot, 'unit_price', '5');
        Svc::updateItem($this->o, $this->snapshot, 'due_date', '2026-11-20');
        Svc::updateItem($this->o, $this->snapshot, 'due_note', '  Sales override  ');
        $row = CoOrderItem::find($this->snapshot);
        $this->assertEquals(1000, (float) $row->unit_price, 'Snapshot price is not editable.');
        $this->assertSame('2026-11-20', $row->due_date->toDateString());
        $this->assertSame('Sales override', $row->due_note);

        Svc::removeItem($this->o, $this->snapshot);
        $this->assertNotNull(CoOrderItem::find($this->snapshot), 'Snapshot line cannot be removed.');
        Svc::removeItem($this->o, $this->manual);
        $this->assertNull(CoOrderItem::find($this->manual), 'Manual line can be removed.');
    }

    public function test_manual_line_recalculates_subtotal_and_service_line_is_added(): void
    {
        Svc::updateItem($this->o, $this->manual, 'qty', '20');
        Svc::updateItem($this->o, $this->manual, 'discount_pct', '50');
        $this->assertEquals(10000, (float) CoOrderItem::find($this->manual)->subtotal);

        Svc::addItem($this->o, 'SERVICE');
        $this->assertSame('SERVICE', CoOrderItem::where('order_id', $this->o->id_order)->orderByDesc('line_seq')->first()->line_kind);
    }

    public function test_delivery_and_external_cost_rows(): void
    {
        Svc::addDelivery($this->o, $this->manual);
        $d = CoOrderDelivery::where('order_id', $this->o->id_order)->firstOrFail();
        $this->assertSame('FULL', $d->shipment_type);
        $this->assertEquals(10, (float) $d->ship_qty);
        Svc::updateDelivery($this->o, $d->id_delivery, 'ship_qty', '4');
        Svc::updateDelivery($this->o, $d->id_delivery, 'status', 'DONE');
        $d->refresh();
        $this->assertEquals(4, (float) $d->ship_qty);
        $this->assertNotSame('DONE', $d->status, 'Only shipment_type, ship_date, ship_qty are editable.');

        Svc::addExternalCost($this->o);
        $c = CoOrderExternalCost::where('order_id', $this->o->id_order)->firstOrFail();
        Svc::updateExternalCost($this->o, $c->id_cost, 'nominal', '75000');
        $this->assertEquals(75000, (float) $c->refresh()->nominal);
        Svc::removeExternalCost($this->o, $c->id_cost);
        Svc::removeDelivery($this->o, $d->id_delivery);
        $this->assertSame(0, CoOrderExternalCost::where('order_id', $this->o->id_order)->count() + CoOrderDelivery::where('order_id', $this->o->id_order)->count());
    }
    // Jalur layar v2: Data\CustomerOrder::save memanggil service per field yang berubah.
    public function test_v2_save_applies_changed_fields_through_the_service(): void
    {
        CoData::addDelivery($this->o, $this->manual);
        $d = CoOrderDelivery::where('order_id', $this->o->id_order)->value('id_delivery');

        CoData::save($this->o->refresh(), [
            'customer_po_no' => 'PO-V2', 'is_ppn' => '1',
            'items' => [$this->manual => ['qty' => '3', 'unit_price' => '100', 'due_date' => '2026-12-05'], $this->snapshot => ['due_date' => '2026-12-06', 'unit_price' => '1']],
            'deliveries' => [$d => ['ship_qty' => '2']],
            'remove_delivery' => [],
        ]);

        $o = CoOrder::find($this->o->id_order);
        $this->assertSame('PO-V2', $o->customer_po_no);
        $this->assertTrue((bool) $o->is_ppn);
        $this->assertEquals(300, (float) CoOrderItem::find($this->manual)->subtotal);
        $this->assertEquals(1000, (float) CoOrderItem::find($this->snapshot)->unit_price);
        $this->assertSame('2026-12-06', CoOrderItem::find($this->snapshot)->due_date->toDateString());
        $this->assertEquals(2, (float) CoOrderDelivery::find($d)->ship_qty);
    }
    public function test_order_type_and_history_timeline(): void
    {
        $o = CoOrder::with('items')->find($this->o->id_order);
        $this->assertSame('Product Only', Svc::orderType($o));
        Svc::addItem($o, 'SERVICE');
        $this->assertSame('Mixed (PRODUCT + SERVICE)', Svc::orderType(CoOrder::with('items')->find($this->o->id_order)));

        $timeline = Svc::historyTimeline(CoOrder::find($this->o->id_order));
        $this->assertSame('Customer Order', end($timeline)['type']);
        $this->assertTrue(end($timeline)['current']);
    }
}