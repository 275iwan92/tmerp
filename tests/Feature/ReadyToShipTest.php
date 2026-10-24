<?php

/*
 * REMARK
 * Tujuan       : Mengunci layar Ready to Ship: kesiapan per CO (ready / partial / waiting), pembuatan satu DO Draft lewat Pull Lines, penolakan CO yang belum siap atau beda alamat.
 * Sumber hulu  : App\Support\V2\Data\ReadyToShip (aturan kuota tetap di DispatchService).
 * Input        : CO CONFIRMED + jadwal kirim buatan test (penanda ZZ-TEST-RTS), alamat kirim tertentu.
 * Output       : Asersi; semua baris buatan test (do_delivery, do_line, co_*, quo_quotation) dihapus di tearDown.
 * Dipakai oleh : php artisan test --filter=ReadyToShipTest (JANGAN dijalankan di database kerja tanpa persetujuan Jo: tes ini menulis baris ZZ-TEST lalu menghapusnya, sama seperti DeliveryOrderEntryTest).
 * Status       : Aktif (2026-10-24).
 */

namespace Tests\Feature;

use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\ReadyToShip;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadyToShipTest extends TestCase
{
    private int $customerId;

    /** @var int[] id quotation buatan test (satu CO = satu quotation, unik) */
    private array $quotations = [];

    /** @var int[] id CO buatan test */
    private array $cos = [];

    /** @var array<int, int[]> id CO => id jadwal kirim */
    private array $sched = [];

    private array $doIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(SecUser::where('is_admin', true)->firstOrFail());
        $this->customerId = (int) DB::connection('tm2mst')->table('cust_customer')->value('id_customer');
    }

    protected function tearDown(): void
    {
        foreach ($this->doIds as $id) {
            DoLine::where('delivery_id', $id)->delete();
            DoDelivery::whereKey($id)->delete();
        }
        foreach ($this->cos as $coId) {
            CoOrderDelivery::where('order_id', $coId)->delete();
            CoOrderItem::where('order_id', $coId)->delete();
            DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->delete();
        }
        DB::connection('tm2sdi')->table('quo_quotation')->whereIn('id_quotation', $this->quotations)->delete();
        parent::tearDown();
    }

    /** CO CONFIRMED dengan dua baris jadwal kirim (100 dan 40 PCS), keduanya Posted to Production. */
    private function makeCo(string $no, string $address): int
    {
        $quotationId = DB::connection('tm2sdi')->table('quo_quotation')->insertGetId([
            'quotation_no' => 'ZZ-TEST-'.substr(md5(microtime().rand()), 0, 8), 'customer_id' => $this->customerId, 'doc_status' => 'PUBLISHED',
            'issued_date' => now()->toDateString(), 'valid_until' => now()->addDays(30)->toDateString(), 'currency_code' => 'IDR', 'created_by' => 'test', 'updated_by' => 'test',
        ]);
        $this->quotations[] = $quotationId;
        $coId = DB::connection('tm2sdi')->table('co_order')->insertGetId([
            'co_no' => $no, 'co_date' => now()->toDateString(), 'quotation_id' => $quotationId, 'customer_id' => $this->customerId,
            'doc_status' => 'CONFIRMED', 'ship_address_detail' => $address, 'created_by' => 'test', 'updated_by' => 'test',
        ]);
        $this->cos[] = $coId;
        foreach ([[100, 'ZZ-TEST item A'], [40, 'ZZ-TEST item B']] as $i => [$qty, $name]) {
            $item = CoOrderItem::create(['order_id' => $coId, 'line_seq' => $i + 1, 'line_kind' => 'PRODUCT', 'description' => $name, 'qty' => $qty, 'qty_unit' => 'PCS',
                'unit_price' => 1000, 'discount_pct' => 0, 'subtotal' => $qty * 1000, 'created_by' => 'test', 'updated_by' => 'test']);
            $this->sched[$coId][] = CoOrderDelivery::create(['order_id' => $coId, 'item_id' => $item->id_item, 'shipment_type' => 'FULL', 'ship_date' => now()->addDay()->toDateString(),
                'ship_qty' => $qty, 'status' => 'PLANNED', 'posted_at' => now(), 'posted_by' => 'test', 'created_by' => 'test', 'updated_by' => 'test'])->id_delivery;
        }

        return $coId;
    }

    private function row(int $coId): ?array
    {
        return collect(ReadyToShip::rows())->firstWhere('id', $coId);
    }

    public function test_state_follows_the_posted_share_of_the_remaining_quota(): void
    {
        $co = $this->makeCo('ZZ-TEST-RTS1', 'ZZ-TEST Jl. Uji 1');
        $row = $this->row($co);
        $this->assertSame('ready', $row['state']);
        $this->assertEqualsWithDelta(140.0, $row['have'], 0.001);
        $this->assertEqualsWithDelta(140.0, $row['want'], 0.001);
        $this->assertSame('PCS', $row['unit']);

        CoOrderDelivery::whereKey($this->sched[$co][1])->update(['posted_at' => null]);
        $row = $this->row($co);
        $this->assertSame('partial', $row['state']);
        $this->assertEqualsWithDelta(100.0, $row['have'], 0.001);

        CoOrderDelivery::whereKey($this->sched[$co][0])->update(['posted_at' => null]);
        $this->assertSame('waiting', $this->row($co)['state']);
    }

    public function test_create_do_pulls_only_posted_lines_through_pull_lines_and_copies_the_address(): void
    {
        $a = $this->makeCo('ZZ-TEST-RTS2', 'ZZ-TEST Jl. Uji 2');
        $b = $this->makeCo('ZZ-TEST-RTS3', 'ZZ-TEST Jl. Uji 2');
        CoOrderDelivery::whereKey($this->sched[$b][1])->update(['posted_at' => null]); // B siap sebagian

        [$do, $message] = ReadyToShip::createDo([$a, $b]);
        $this->assertNotNull($do, $message);
        $this->doIds[] = $do->id_delivery;
        $this->assertSame('DRAFT', $do->status);
        $this->assertSame('ZZ-TEST Jl. Uji 2', $do->ship_address_detail);
        $this->assertCount(3, DoLine::where('delivery_id', $do->id_delivery)->get(), 'A: 2 baris, B: hanya baris yang Posted');
        $this->assertEqualsWithDelta(0.0, (float) DB::connection('tm2sdi')->table('v_co_delivery_outstanding')->where('co_delivery_id', $this->sched[$a][0])->value('qty_available'), 0.001);

        // CO yang sama tidak bisa dibuatkan DO kedua: kuotanya sudah terambil.
        [$again, $msg] = ReadyToShip::createDo([$a]);
        $this->assertNull($again);
        $this->assertNotSame('', $msg);
    }

    public function test_waiting_unknown_or_mixed_address_selections_are_refused_and_create_no_draft(): void
    {
        $a = $this->makeCo('ZZ-TEST-RTS4', 'ZZ-TEST Jl. Uji 4');
        $b = $this->makeCo('ZZ-TEST-RTS5', 'ZZ-TEST Jl. Lain 5');
        $before = DoDelivery::count();

        [$do, $msg] = ReadyToShip::createDo([$a, $b]);
        $this->assertNull($do);
        $this->assertSame(t('Choose CO from the same customer, address and tax status.'), $msg);

        [$do, $msg] = ReadyToShip::createDo([]);
        $this->assertNull($do);
        $this->assertSame(t('Choose CO that are ready to ship.'), $msg);

        [$do] = ReadyToShip::createDo([999999999]);
        $this->assertNull($do);

        CoOrderDelivery::whereIn('id_delivery', $this->sched[$a])->update(['posted_at' => null]);
        [$do] = ReadyToShip::createDo([$a]);
        $this->assertNull($do, 'CO yang belum Posted to Production menunggu produksi');
        $this->assertSame($before, DoDelivery::count());
    }
}
