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

// DISPATCH-02 (no-merge rule) -- dispatch_tier2.md §B.4, WAJIB test unit
// tersendiri (checklist dispatch_tier4.md §9), bukan cuma dicek manual. Dua
// co_order_item_id berbeda dgn item_description IDENTIK tapi unit_price
// beda SELALU jadi 2 baris do_line terpisah, tidak pernah digabung/dirata2.
class DispatchNoMergeRuleTest extends TestCase
{
    protected array $cleanupCoIds = [];

    protected array $cleanupQuotationIds = [];

    protected ?int $customerId = null;

    protected bool $customerCreated = false;

    protected ?DoDelivery $do = null;

    protected function tearDown(): void
    {
        if ($this->do) {
            DoLine::where('delivery_id', $this->do->id_delivery)->delete();
            $this->do->delete();
        }

        foreach ($this->cleanupCoIds as $coId) {
            CoOrderDelivery::where('order_id', $coId)->delete();
            CoOrderItem::where('order_id', $coId)->delete();
            DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->delete();
        }

        foreach ($this->cleanupQuotationIds as $quotationId) {
            DB::connection('tm2sdi')->table('quo_quotation')->where('id_quotation', $quotationId)->delete();
        }

        if ($this->customerCreated && $this->customerId) {
            DB::connection('tm2mst')->table('cust_customer')->where('id_customer', $this->customerId)->delete();
        }

        parent::tearDown();
    }

    protected function makeCo(string $coNo, string $description, float $qty, float $price): array
    {
        $login = 'test';

        $quotationId = DB::connection('tm2sdi')->table('quo_quotation')->insertGetId([
            'quotation_no' => 'Q'.substr(md5($coNo.microtime()), 0, 12),
            'customer_id' => $this->customerId,
            'doc_status' => 'PUBLISHED',
            'issued_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency_code' => 'IDR',
            'created_by' => $login, 'updated_by' => $login,
        ]);
        $this->cleanupQuotationIds[] = $quotationId;

        $coId = DB::connection('tm2sdi')->table('co_order')->insertGetId([
            'co_no' => $coNo, 'co_date' => now()->toDateString(), 'quotation_id' => $quotationId,
            'doc_status' => 'CONFIRMED', 'created_by' => $login, 'updated_by' => $login,
        ]);
        $this->cleanupCoIds[] = $coId;

        $itemId = CoOrderItem::create([
            'order_id' => $coId, 'line_seq' => 1, 'line_kind' => 'PRODUCT',
            'description' => $description, 'qty' => $qty, 'qty_unit' => 'PCS',
            'unit_price' => $price, 'discount_pct' => 0, 'subtotal' => $qty * $price,
            'created_by' => $login, 'updated_by' => $login,
        ])->id_item;

        $deliveryId = CoOrderDelivery::create([
            'order_id' => $coId, 'item_id' => $itemId, 'shipment_type' => 'FULL',
            'ship_date' => now()->addDay()->toDateString(), 'ship_qty' => $qty, 'status' => 'PLANNED',
            'created_by' => $login, 'updated_by' => $login,
        ])->id_delivery;

        return [$coId, $itemId, $deliveryId];
    }

    public function test_two_lines_with_same_item_name_but_different_price_are_never_merged(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin);
        Auth::setUser($admin);

        $this->customerId = DB::connection('tm2mst')->table('cust_customer')->value('id_customer');

        if (! $this->customerId) {
            $this->customerId = DB::connection('tm2mst')->table('cust_customer')->insertGetId([
                'customer_code' => 'CUST-TEST-NOMERGE', 'customer_name' => 'Customer Test No-Merge',
                'is_active' => 1, 'activate' => 1, 'created_by' => 'test', 'updated_by' => 'test',
            ]);
            $this->customerCreated = true;
        }

        [, , $delivery1] = $this->makeCo('CO-TEST-A', 'Box Karton 5-Ply RSC', 500, 12000);
        [, , $delivery2] = $this->makeCo('CO-TEST-B', 'Box Karton 5-Ply RSC', 300, 13500);

        $this->do = DoDelivery::create([
            'do_date' => now()->toDateString(), 'customer_id' => $this->customerId, 'status' => 'DRAFT',
            'created_by' => 'test', 'updated_by' => 'test',
        ]);

        $svc = app(DispatchService::class);
        $result = $svc->pullLines($this->do, [$delivery1, $delivery2], 'test');

        $this->assertTrue($result['ok']);

        $lines = DoLine::where('delivery_id', $this->do->id_delivery)->get();

        // Aturan inti: TIDAK PERNAH 1 baris gabungan qty 800 dengan 1 harga.
        $this->assertCount(2, $lines, 'Dua co_order_item_id berbeda harus jadi 2 baris do_line, tidak digabung.');
        $this->assertNotEquals($lines->first()->co_order_item_id, $lines->last()->co_order_item_id);

        $prices = $lines->pluck('unit_price')->map(fn ($p) => (float) $p)->sort()->values();
        $this->assertEqualsWithDelta(12000.0, $prices[0], 0.01);
        $this->assertEqualsWithDelta(13500.0, $prices[1], 0.01);

        // Total nilai = SUM(qty*harga) per baris, BUKAN qty gabungan * harga rata-rata.
        $totalValue = $lines->sum('subtotal');
        $expected = (500 * 12000) + (300 * 13500);
        $this->assertEqualsWithDelta((float) $expected, (float) $totalValue, 0.01);

        // Hapus salah satu baris -- baris yang tersisa TIDAK berubah harga/qty (Tier2 §B.4 poin 3).
        $lineToRemove = $lines->firstWhere('unit_price', 12000);
        $remainingLineId = $lines->firstWhere('unit_price', 13500)->id_line;

        $svc->removeLine(DoLine::find($lineToRemove->id_line));

        $remaining = DoLine::find($remainingLineId);
        $this->assertEqualsWithDelta(300.0, (float) $remaining->qty_do, 0.01);
        $this->assertEqualsWithDelta(13500.0, (float) $remaining->unit_price, 0.01);
    }

    public function test_pulling_same_co_delivery_twice_into_same_do_is_rejected_not_merged(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin);
        Auth::setUser($admin);

        $this->customerId = DB::connection('tm2mst')->table('cust_customer')->value('id_customer');

        if (! $this->customerId) {
            $this->customerId = DB::connection('tm2mst')->table('cust_customer')->insertGetId([
                'customer_code' => 'CUST-TEST-DUP', 'customer_name' => 'Customer Test Duplicate Pull',
                'is_active' => 1, 'activate' => 1, 'created_by' => 'test', 'updated_by' => 'test',
            ]);
            $this->customerCreated = true;
        }

        [, , $deliveryId] = $this->makeCo('CO-TEST-DUP', 'Sleeve Karton', 200, 3400);

        $this->do = DoDelivery::create([
            'do_date' => now()->toDateString(), 'customer_id' => $this->customerId, 'status' => 'DRAFT',
            'created_by' => 'test', 'updated_by' => 'test',
        ]);

        $svc = app(DispatchService::class);
        $svc->pullLines($this->do, [$deliveryId], 'test');
        // Klik ganda -- tarik co_delivery_id yang sama lagi ke DO yang sama (Tier2 §A.4).
        $svc->pullLines($this->do, [$deliveryId], 'test');

        $lines = DoLine::where('delivery_id', $this->do->id_delivery)->get();

        $this->assertCount(1, $lines, 'Menarik co_delivery_id yang sama dua kali TIDAK boleh menambah qty ke baris existing atau membuat baris kedua.');
        $this->assertEqualsWithDelta(200.0, (float) $lines->first()->qty_do, 0.01);
    }
}
