<?php

namespace Tests\Feature;

use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use App\Models\Sys\SecUser;
use App\Services\Dispatch\DispatchService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// dispatch_tier2.md §C (status lifecycle) + §F (Sisi A/B cetak, DISPATCH-08).
class DispatchLifecycleTest extends TestCase
{
    protected ?int $coId = null;

    protected ?int $quotationId = null;

    protected ?int $customerId = null;

    protected bool $customerCreated = false;

    protected ?DoDelivery $do = null;

    protected function tearDown(): void
    {
        if ($this->do) {
            DoLine::where('delivery_id', $this->do->id_delivery)->delete();
            $this->do->delete();
        }
        if ($this->coId) {
            CoOrderDelivery::where('order_id', $this->coId)->delete();
            CoOrderItem::where('order_id', $this->coId)->delete();
            DB::connection('tm2sdi')->table('co_order')->where('id_order', $this->coId)->delete();
        }
        if ($this->quotationId) {
            DB::connection('tm2sdi')->table('quo_quotation')->where('id_quotation', $this->quotationId)->delete();
        }
        if ($this->customerCreated && $this->customerId) {
            DB::connection('tm2mst')->table('cust_customer')->where('id_customer', $this->customerId)->delete();
        }

        parent::tearDown();
    }

    protected function setUpCustomerAndCo(float $qty, float $price): int
    {
        $this->customerId = DB::connection('tm2mst')->table('cust_customer')->value('id_customer');

        if (! $this->customerId) {
            $this->customerId = DB::connection('tm2mst')->table('cust_customer')->insertGetId([
                'customer_code' => 'CUST-TEST-LIFECYCLE', 'customer_name' => 'Customer Test Lifecycle',
                'is_active' => 1, 'activate' => 1, 'created_by' => 'test', 'updated_by' => 'test',
            ]);
            $this->customerCreated = true;
        }

        $this->quotationId = DB::connection('tm2sdi')->table('quo_quotation')->insertGetId([
            'quotation_no' => 'Q'.substr(md5(microtime().rand()), 0, 12),
            'customer_id' => $this->customerId, 'doc_status' => 'PUBLISHED',
            'issued_date' => now()->toDateString(), 'valid_until' => now()->addDays(30)->toDateString(),
            'currency_code' => 'IDR', 'created_by' => 'test', 'updated_by' => 'test',
        ]);

        $this->coId = DB::connection('tm2sdi')->table('co_order')->insertGetId([
            'co_no' => 'CO-TEST-LC', 'co_date' => now()->toDateString(), 'quotation_id' => $this->quotationId,
            'doc_status' => 'CONFIRMED', 'created_by' => 'test', 'updated_by' => 'test',
        ]);

        $itemId = CoOrderItem::create([
            'order_id' => $this->coId, 'line_seq' => 1, 'line_kind' => 'PRODUCT',
            'description' => 'ITEM-RAHASIA-HARGA', 'qty' => $qty, 'qty_unit' => 'PCS',
            'unit_price' => $price, 'discount_pct' => 0, 'subtotal' => $qty * $price,
            'created_by' => 'test', 'updated_by' => 'test',
        ])->id_item;

        return CoOrderDelivery::create([
            'order_id' => $this->coId, 'item_id' => $itemId, 'shipment_type' => 'FULL',
            'ship_date' => now()->addDay()->toDateString(), 'ship_qty' => $qty, 'status' => 'PLANNED',
            'created_by' => 'test', 'updated_by' => 'test',
        ])->id_delivery;
    }

    public function test_full_lifecycle_draft_to_confirmed_to_shipped_recalculates_co_delivery_status(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin);
        Auth::setUser($admin);

        $deliveryId = $this->setUpCustomerAndCo(qty: 100, price: 5000);

        $this->do = DoDelivery::create([
            'do_date' => now()->toDateString(), 'customer_id' => $this->customerId, 'status' => 'DRAFT',
            'created_by' => 'test', 'updated_by' => 'test',
        ]);

        $svc = app(DispatchService::class);

        $this->assertNull($this->do->do_no);

        $svc->pullLines($this->do, [$deliveryId], 'test');
        $confirmResult = $svc->confirm($this->do, 'test');
        $this->assertTrue($confirmResult['ok']);

        $this->do->refresh();
        $this->assertSame('CONFIRMED', $this->do->status);
        $this->assertMatchesRegularExpression('#^DO/\d{2}/\d{5}$#', $this->do->do_no);

        $shipResult = $svc->markShipped($this->do, 'test');
        $this->assertTrue($shipResult['ok']);

        $this->do->refresh();
        $this->assertSame('SHIPPED', $this->do->status);
        $this->assertNotNull($this->do->actual_ship_date);

        // Full qty shipped -> co_order_delivery status ikut SHIPPED (Tier2 §C.3).
        $this->assertSame('SHIPPED', CoOrderDelivery::whereKey($deliveryId)->value('status'));

        // Cancel dari SHIPPED harus ditolak (Tier2 §C.4).
        $cancelResult = $svc->cancel($this->do, 'coba batalkan', 'test');
        $this->assertFalse($cancelResult['ok']);
    }

    public function test_cancel_from_confirmed_restores_qty_available_and_requires_reason(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin);
        Auth::setUser($admin);

        $deliveryId = $this->setUpCustomerAndCo(qty: 50, price: 8000);

        $this->do = DoDelivery::create([
            'do_date' => now()->toDateString(), 'customer_id' => $this->customerId, 'status' => 'DRAFT',
            'created_by' => 'test', 'updated_by' => 'test',
        ]);

        $svc = app(DispatchService::class);
        $svc->pullLines($this->do, [$deliveryId], 'test');
        $svc->confirm($this->do, 'test');
        $this->do->refresh();

        $availableBeforeCancel = (float) DB::connection('tm2sdi')->table('v_co_delivery_outstanding')
            ->where('co_delivery_id', $deliveryId)->value('qty_available');
        $this->assertEqualsWithDelta(0.0, $availableBeforeCancel, 0.01, 'Qty sudah terserap DO CONFIRMED.');

        // Wajib alasan.
        $rejected = $svc->cancel($this->do, '', 'test');
        $this->assertFalse($rejected['ok']);

        $cancelled = $svc->cancel($this->do, 'salah alamat kirim', 'test');
        $this->assertTrue($cancelled['ok']);

        $this->do->refresh();
        $this->assertSame('CANCELLED', $this->do->status);

        // qty_available pulih otomatis (Tier2 §C.4) tanpa aksi manual ke co_order_delivery.
        $availableAfterCancel = (float) DB::connection('tm2sdi')->table('v_co_delivery_outstanding')
            ->where('co_delivery_id', $deliveryId)->value('qty_available');
        $this->assertEqualsWithDelta(50.0, $availableAfterCancel, 0.01);
    }

    public function test_delivery_order_print_shows_price_but_surat_jalan_never_does(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin);
        Auth::setUser($admin);

        $deliveryId = $this->setUpCustomerAndCo(qty: 10, price: 777777);

        $this->do = DoDelivery::create([
            'do_date' => now()->toDateString(), 'customer_id' => $this->customerId, 'status' => 'DRAFT',
            'created_by' => 'test', 'updated_by' => 'test',
        ]);

        $svc = app(DispatchService::class);
        $svc->pullLines($this->do, [$deliveryId], 'test');
        $svc->confirm($this->do, 'test');
        $this->do->refresh();

        $doHtml = $this->get('/v2/print/delivery-order/'.$this->do->id_delivery)->assertOk()->getContent();
        $this->assertStringContainsString('ITEM-RAHASIA-HARGA', $doHtml);
        $this->assertStringContainsString('777.777', $doHtml);

        $sjHtml = $this->get('/v2/print/delivery-note/'.$this->do->id_delivery)->assertOk()->getContent();
        $this->assertStringContainsString('ITEM-RAHASIA-HARGA', $sjHtml);
        $this->assertStringNotContainsString('777.777', $sjHtml);
        $this->assertStringNotContainsString('777777', $sjHtml);
    }
}
