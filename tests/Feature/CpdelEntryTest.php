<?php

/*
 * REMARK
 * Tujuan       : Mengunci aturan Split / Pindah tanggal dan Gabung jadwal kirim CPDEL lewat kelas Data v2, termasuk pesan penolakan.
 * Sumber hulu  : App\Support\V2\Data\Cpdel::split / merge (aturan: CpdelEntryService).
 * Input        : CO CONFIRMED + dua produk + jadwal kirim PLANNED buatan test (penanda ZZ-TEST).
 * Output       : Asersi; semua baris buatan test (co_order_delivery, co_order_item, co_order, quo_quotation) dihapus di tearDown dan diverifikasi.
 * Dipakai oleh : php artisan test --filter=CpdelEntryTest.
 * Status       : Aktif (T10 tahap 7c, 2026-10-22).
 */

namespace Tests\Feature;

use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\Cpdel;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CpdelEntryTest extends TestCase
{
    private int $quotationId;

    private int $coId;

    private int $itemA;

    private int $itemB;

    /** @var int jadwal kirim A qty 100 */
    private int $main;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(SecUser::where('is_admin', true)->firstOrFail());
        $customerId = (int) DB::connection('tm2mst')->table('cust_customer')->value('id_customer');

        $this->quotationId = DB::connection('tm2sdi')->table('quo_quotation')->insertGetId([
            'quotation_no' => 'ZZ-TEST-'.substr(md5(microtime().rand()), 0, 8), 'customer_id' => $customerId, 'doc_status' => 'PUBLISHED',
            'issued_date' => now()->toDateString(), 'valid_until' => now()->addDays(30)->toDateString(), 'currency_code' => 'IDR', 'created_by' => 'test', 'updated_by' => 'test',
        ]);
        $this->coId = DB::connection('tm2sdi')->table('co_order')->insertGetId([
            'co_no' => 'ZZ-TEST-CPE', 'co_date' => now()->toDateString(), 'quotation_id' => $this->quotationId, 'customer_id' => $customerId,
            'doc_status' => 'CONFIRMED', 'created_by' => 'test', 'updated_by' => 'test',
        ]);
        $mk = fn (int $seq, string $name) => CoOrderItem::create(['order_id' => $this->coId, 'line_seq' => $seq, 'line_kind' => 'PRODUCT', 'description' => $name, 'qty' => 100, 'qty_unit' => 'PCS',
            'unit_price' => 1000, 'discount_pct' => 0, 'subtotal' => 100000, 'created_by' => 'test', 'updated_by' => 'test'])->id_item;
        $this->itemA = $mk(1, 'ZZ-TEST item A');
        $this->itemB = $mk(2, 'ZZ-TEST item B');
        $this->main = $this->row($this->itemA, '2026-11-10', 100);
    }

    protected function tearDown(): void
    {
        CoOrderDelivery::where('order_id', $this->coId)->delete();
        CoOrderItem::where('order_id', $this->coId)->delete();
        DB::connection('tm2sdi')->table('co_order')->where('id_order', $this->coId)->delete();
        DB::connection('tm2sdi')->table('quo_quotation')->where('id_quotation', $this->quotationId)->delete();

        $this->assertSame(0, CoOrderDelivery::where('order_id', $this->coId)->count() + CoOrderItem::where('order_id', $this->coId)->count());
        $this->assertSame(0, DB::connection('tm2sdi')->table('co_order')->where('id_order', $this->coId)->count()
            + DB::connection('tm2sdi')->table('quo_quotation')->where('id_quotation', $this->quotationId)->count());
        parent::tearDown();
    }

    private function row(int $item, string $date, float $qty, string $status = 'PLANNED'): int
    {
        return CoOrderDelivery::create(['order_id' => $this->coId, 'item_id' => $item, 'shipment_type' => 'FULL', 'ship_date' => $date, 'ship_qty' => $qty,
            'status' => $status, 'created_by' => 'test', 'updated_by' => 'test'])->id_delivery;
    }

    private function rows(int $item)
    {
        return CoOrderDelivery::where('order_id', $this->coId)->where('item_id', $item)->orderBy('id_delivery')->get();
    }

    public function test_split_without_qty_only_moves_the_date_of_the_same_row(): void
    {
        $this->assertNull(Cpdel::split($this->main, null, '2026-11-20'));
        $this->assertNull(Cpdel::split($this->main, '', '2026-11-21'));
        $rows = $this->rows($this->itemA);
        $this->assertCount(1, $rows);
        $this->assertSame('2026-11-21', $rows[0]->ship_date->toDateString());
        $this->assertEqualsWithDelta(100.0, (float) $rows[0]->ship_qty, 0.001);
        $this->assertSame('FULL', $rows[0]->shipment_type);
    }

    public function test_split_with_qty_creates_a_partial_planned_row_and_keeps_the_total(): void
    {
        $this->assertNull(Cpdel::split($this->main, '30', '2026-11-25'));
        $rows = $this->rows($this->itemA);
        $this->assertCount(2, $rows);
        [$old, $new] = [$rows[0], $rows[1]];
        $this->assertSame($this->main, (int) $old->id_delivery);
        $this->assertEqualsWithDelta(70.0, (float) $old->ship_qty, 0.001);
        $this->assertSame('2026-11-10', $old->ship_date->toDateString());
        $this->assertSame('PARTIAL', $old->shipment_type);
        $this->assertEqualsWithDelta(30.0, (float) $new->ship_qty, 0.001);
        $this->assertSame('2026-11-25', $new->ship_date->toDateString());
        $this->assertSame('PARTIAL', $new->shipment_type);
        $this->assertSame('PLANNED', $new->status);
        $this->assertSame($this->main, (int) $new->split_from_id);
    }

    public function test_split_refusals_keep_the_data_unchanged(): void
    {
        $limit = 'Qty split harus lebih dari 0 dan kurang dari qty baris ini (100.00).';
        $this->assertSame($limit, Cpdel::split($this->main, '0', '2026-11-25'));
        $this->assertSame($limit, Cpdel::split($this->main, '-5', '2026-11-25'));
        $this->assertSame($limit, Cpdel::split($this->main, '100', '2026-11-25'));
        $this->assertSame($limit, Cpdel::split($this->main, '150', '2026-11-25'));
        $this->assertCount(1, $this->rows($this->itemA));
        $this->assertSame('2026-11-10', $this->rows($this->itemA)[0]->ship_date->toDateString());

        $shipped = $this->row($this->itemB, '2026-11-12', 100, 'SHIPPED');
        $this->assertSame('Baris ini sudah SHIPPED, tidak bisa diubah.', Cpdel::split($shipped, '10', '2026-11-30'));
        $this->assertSame('Baris ini sudah SHIPPED, tidak bisa diubah.', Cpdel::split($shipped, null, '2026-11-30'));
        $this->assertSame('2026-11-12', CoOrderDelivery::find($shipped)->ship_date->toDateString());
    }

    public function test_split_of_a_posted_row_is_refused_before_the_rule_runs(): void
    {
        CoOrderDelivery::whereKey($this->main)->update(['posted_at' => now(), 'posted_by' => 'test']);
        $this->assertSame(t('Posted to Production (Work Order created): date and qty can no longer change here.'), Cpdel::split($this->main, '10', '2026-11-25'));
        $this->assertCount(1, $this->rows($this->itemA));
    }

    public function test_merge_joins_planned_rows_of_the_same_order_and_product_into_the_earliest_date(): void
    {
        $b = $this->row($this->itemA, '2026-11-05', 20);
        $c = $this->row($this->itemA, '2026-11-30', 5);

        $this->assertNull(Cpdel::merge([$this->main, $b, $c]));
        $rows = $this->rows($this->itemA);
        $this->assertCount(1, $rows);
        $this->assertSame($b, (int) $rows[0]->id_delivery, 'Baris bertanggal paling awal menang.');
        $this->assertEqualsWithDelta(125.0, (float) $rows[0]->ship_qty, 0.001);
        $this->assertSame('2026-11-05', $rows[0]->ship_date->toDateString());
    }

    public function test_merge_refusals_keep_the_data_unchanged(): void
    {
        $b = $this->row($this->itemA, '2026-11-05', 20);
        $otherProduct = $this->row($this->itemB, '2026-11-06', 10);
        $shipped = $this->row($this->itemA, '2026-11-07', 10, 'SHIPPED');

        $this->assertSame('Pilih minimal 2 baris utk digabung.', Cpdel::merge([]));
        $this->assertSame('Pilih minimal 2 baris utk digabung.', Cpdel::merge([$b]));
        $this->assertSame('Baris yang digabung harus dari CO & produk yang sama.', Cpdel::merge([$b, $otherProduct]));
        $this->assertSame('Hanya baris berstatus PLANNED yang bisa digabung.', Cpdel::merge([$b, $shipped]));

        $this->assertCount(3, $this->rows($this->itemA));
        $this->assertEqualsWithDelta(20.0, (float) CoOrderDelivery::find($b)->ship_qty, 0.001);

        CoOrderDelivery::whereKey($b)->update(['posted_at' => now(), 'posted_by' => 'test']);
        $this->assertSame(t('Lines Posted to Production cannot be joined.'), Cpdel::merge([$this->main, $b]));
        $this->assertCount(3, $this->rows($this->itemA));
    }
}
