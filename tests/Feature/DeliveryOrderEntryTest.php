<?php

/*
 * REMARK
 * Tujuan       : Mengunci aturan entri Delivery Order lewat kelas Data v2 (header, alamat, tarik baris, ubah qty/harga, hapus baris, confirm / ship / cancel dan penolakannya).
 * Sumber hulu  : App\Support\V2\Data\DeliveryOrder (aturan: DeliveryOrderEntryService -> DispatchService).
 * Input        : CO CONFIRMED + jadwal kirim Posted buatan test (penanda ZZ-TEST), DO Draft.
 * Output       : Asersi; semua baris buatan test (do_delivery, do_line, co_*, quo_quotation) dihapus di tearDown dan diverifikasi.
 * Dipakai oleh : php artisan test --filter=DeliveryOrderEntryTest.
 * Status       : Aktif (T10 tahap 7c, 2026-10-22).
 */

namespace Tests\Feature;

use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\DeliveryOrder as Data;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeliveryOrderEntryTest extends TestCase
{
    private int $customerId;

    private int $quotationId;

    private int $coId;

    /** @var int[] id jadwal kirim CO (A qty 100 @ 5000, B qty 40 @ 2000) */
    private array $sched = [];

    private ?int $doId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(SecUser::where('is_admin', true)->firstOrFail());
        $this->customerId = (int) DB::connection('tm2mst')->table('cust_customer')->value('id_customer');

        $this->quotationId = DB::connection('tm2sdi')->table('quo_quotation')->insertGetId([
            'quotation_no' => 'ZZ-TEST-'.substr(md5(microtime().rand()), 0, 8), 'customer_id' => $this->customerId, 'doc_status' => 'PUBLISHED',
            'issued_date' => now()->toDateString(), 'valid_until' => now()->addDays(30)->toDateString(), 'currency_code' => 'IDR', 'created_by' => 'test', 'updated_by' => 'test',
        ]);
        $this->coId = DB::connection('tm2sdi')->table('co_order')->insertGetId([
            'co_no' => 'ZZ-TEST-DOE', 'ship_address_detail' => 'ZZ-TEST Jl. Uji 1', 'co_date' => now()->toDateString(), 'quotation_id' => $this->quotationId, 'customer_id' => $this->customerId,
            'doc_status' => 'CONFIRMED', 'created_by' => 'test', 'updated_by' => 'test',
        ]);
        foreach ([[100, 5000, 'ZZ-TEST item A'], [40, 2000, 'ZZ-TEST item B']] as $i => [$qty, $price, $name]) {
            $item = CoOrderItem::create(['order_id' => $this->coId, 'line_seq' => $i + 1, 'line_kind' => 'PRODUCT', 'description' => $name, 'qty' => $qty, 'qty_unit' => 'PCS',
                'unit_price' => $price, 'discount_pct' => 0, 'subtotal' => $qty * $price, 'created_by' => 'test', 'updated_by' => 'test']);
            $this->sched[] = CoOrderDelivery::create(['order_id' => $this->coId, 'item_id' => $item->id_item, 'shipment_type' => 'FULL', 'ship_date' => now()->addDay()->toDateString(),
                'ship_qty' => $qty, 'status' => 'PLANNED', 'posted_at' => now(), 'posted_by' => 'test', 'created_by' => 'test', 'updated_by' => 'test'])->id_delivery;
        }
        $this->doId = Data::create($this->customerId)->id_delivery;
    }

    protected function tearDown(): void
    {
        if ($this->doId) {
            DoLine::where('delivery_id', $this->doId)->delete();
            DoDelivery::whereKey($this->doId)->delete();
        }
        CoOrderDelivery::where('order_id', $this->coId)->delete();
        CoOrderItem::where('order_id', $this->coId)->delete();
        DB::connection('tm2sdi')->table('co_order')->where('id_order', $this->coId)->delete();
        DB::connection('tm2sdi')->table('quo_quotation')->where('id_quotation', $this->quotationId)->delete();

        $this->assertSame(0, DoDelivery::where('id_delivery', $this->doId)->count() + DoLine::where('delivery_id', $this->doId)->count());
        $this->assertSame(0, CoOrderDelivery::where('order_id', $this->coId)->count() + CoOrderItem::where('order_id', $this->coId)->count());
        $this->assertSame(0, DB::connection('tm2sdi')->table('co_order')->where('id_order', $this->coId)->count()
            + DB::connection('tm2sdi')->table('quo_quotation')->where('id_quotation', $this->quotationId)->count());
        parent::tearDown();
    }

    private function do(): DoDelivery
    {
        return DoDelivery::findOrFail($this->doId);
    }

    private function lines()
    {
        return DoLine::where('delivery_id', $this->doId)->orderBy('line_no')->get();
    }

    private function pullBoth(): void
    {
        $this->assertSame('', Data::pull($this->do(), $this->sched));
    }

    public function test_header_fields_empty_value_becomes_null_and_address_comes_from_geo_lookup(): void
    {
        $err = Data::save($this->do(), ['do_date' => '2026-11-02', 'scheduled_ship_date' => '2026-11-05', 'ship_address_detail' => 'ZZ-TEST Jl. Uji 1']);
        $this->assertSame('', $err);
        $do = $this->do();
        $this->assertSame('2026-11-02', $do->do_date->toDateString());
        $this->assertSame('2026-11-05', $do->scheduled_ship_date->toDateString());
        $this->assertSame('ZZ-TEST Jl. Uji 1', $do->ship_address_detail);

        Data::save($this->do(), ['scheduled_ship_date' => '', 'ship_address_detail' => '']);
        $do = $this->do();
        $this->assertNull($do->scheduled_ship_date);
        $this->assertNull($do->ship_address_detail);

        $geo = DB::connection('tm2mst')->table('v_geo_full_address')->whereNotNull('id_village')->first(['id_village', 'full_label', 'postal_code']);
        if ($geo) {
            Data::save($this->do(), ['ship_area' => 'VILLAGE:'.$geo->id_village]);
            $do = $this->do();
            $this->assertSame((int) $geo->id_village, (int) $do->ship_area_id);
            $this->assertSame('VILLAGE', $do->ship_area_level);
            $this->assertSame($geo->full_label, $do->ship_address_label);
            $this->assertSame($geo->postal_code, $do->ship_postal_code);
        }
    }

    public function test_pull_creates_one_line_per_schedule_row_and_rejects_unposted_or_unknown_rows(): void
    {
        $this->pullBoth();
        $lines = $this->lines();
        $this->assertCount(2, $lines);
        $this->assertSame([1, 2], $lines->pluck('line_no')->map(fn ($n) => (int) $n)->all());
        $this->assertEqualsWithDelta(100.0, (float) $lines[0]->qty_do, 0.001);
        $this->assertEqualsWithDelta(5000.0, (float) $lines[0]->unit_price, 0.001);
        $this->assertEqualsWithDelta(500000.0, (float) $lines[0]->subtotal, 0.001);
        $this->assertEqualsWithDelta(80000.0, (float) $lines[1]->subtotal, 0.001);
        $this->assertSame('PLANNED', $lines[0]->status);

        // Tarik ulang baris yang sama = diabaikan diam-diam (bukan dobel, bukan error).
        $this->assertSame('', Data::pull($this->do()->refresh(), $this->sched));
        $this->assertCount(2, $this->lines());

        // Jadwal kirim yang belum di-Post to Production ditolak sebelum menyentuh DO.
        CoOrderDelivery::whereKey($this->sched[1])->update(['posted_at' => null]);
        DoLine::where('delivery_id', $this->doId)->delete();
        $this->assertSame(t('The chosen delivery schedule is not Posted to Production yet in Customer Plan Delivery.'), Data::pull($this->do(), [$this->sched[1]]));
        $this->assertCount(0, $this->lines());

        // Id yang tidak ada = pesan error DispatchService.
        $this->assertSame(tf('Schedule line #:id is not found.', ['id' => 999999999]), Data::pull($this->do(), [999999999]));
    }

    public function test_line_qty_and_price_edits_enforce_limits_and_flag_price_override(): void
    {
        $this->pullBoth();
        $a = $this->lines()[0];

        $this->assertSame('', Data::save($this->do(), ['lines' => [$a->id_line => ['qty_do' => '30']]]));
        $a = DoLine::find($a->id_line);
        $this->assertEqualsWithDelta(30.0, (float) $a->qty_do, 0.001);
        $this->assertEqualsWithDelta(150000.0, (float) $a->subtotal, 0.001);

        $this->assertSame(t('The qty must be more than 0.'), Data::save($this->do(), ['lines' => [$a->id_line => ['qty_do' => '0']]]));
        $this->assertSame(tf('The qty exceeds the available limit (:qty).', ['qty' => 100.0]), Data::save($this->do(), ['lines' => [$a->id_line => ['qty_do' => '500']]]));
        $this->assertEqualsWithDelta(30.0, (float) DoLine::find($a->id_line)->qty_do, 0.001, 'Penolakan tidak mengubah qty.');

        $this->assertFalse((bool) $this->do()->price_change_flag);
        $this->assertSame('', Data::save($this->do(), ['lines' => [$a->id_line => ['unit_price' => '6000']]]));
        $a = DoLine::find($a->id_line);
        $this->assertEqualsWithDelta(6000.0, (float) $a->unit_price, 0.001);
        $this->assertEqualsWithDelta(5000.0, (float) $a->original_unit_price, 0.001);
        $this->assertTrue((bool) $a->price_overridden);
        $this->assertEqualsWithDelta(180000.0, (float) $a->subtotal, 0.001);
        $this->assertTrue((bool) $this->do()->price_change_flag);

        // Ubah harga lagi: original_unit_price tetap harga CO pertama.
        Data::save($this->do(), ['lines' => [$a->id_line => ['unit_price' => '7000']]]);
        $this->assertEqualsWithDelta(5000.0, (float) DoLine::find($a->id_line)->original_unit_price, 0.001);

        $this->assertSame(t('The price cannot be negative.'), Data::save($this->do(), ['lines' => [$a->id_line => ['unit_price' => '-1']]]));
    }

    public function test_remove_line_deletes_only_that_line_and_foreign_line_id_is_not_found(): void
    {
        $this->pullBoth();
        [$a, $b] = [$this->lines()[0], $this->lines()[1]];

        Data::save($this->do(), ['remove' => [$a->id_line => '1']]);
        $this->assertSame([$b->id_line], $this->lines()->pluck('id_line')->all());

        // Baris milik DO lain tidak boleh disentuh: findOrFail terhadap delivery_id DO ini.
        $other = Data::create($this->customerId);
        try {
            $this->expectException(ModelNotFoundException::class);
            Data::save($other, ['remove' => [$b->id_line => '1']]);
        } finally {
            DoDelivery::whereKey($other->id_delivery)->delete();
        }
    }

    public function test_confirm_requires_lines_issues_number_locks_the_draft_and_ship_closes_co_schedule(): void
    {
        [$ok, $msg] = Data::act($this->do(), 'confirm');
        $this->assertFalse($ok);
        $this->assertSame(t('At least 1 line is required before Confirm.'), $msg);

        $this->pullBoth();
        [$ok] = Data::act($this->do(), 'confirm');
        $this->assertTrue($ok);
        $do = $this->do();
        $this->assertSame('CONFIRMED', $do->status);
        $this->assertMatchesRegularExpression('#^DO/\d{2}/\d{5}$#', $do->do_no);

        [$ok, $msg] = Data::act($do, 'confirm');
        $this->assertTrue($ok, 'Quirk v1: ok = status akhir sama dengan target, walau aksi ditolak.');
        $this->assertSame(t('The document no longer has status DRAFT.'), $msg);

        // Header / baris terkunci setelah Confirm.
        try {
            Data::save($do, ['do_date' => '2026-12-01']);
            $this->fail('save() harus abort 422 saat DO bukan DRAFT.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        [$ok] = Data::act($this->do(), 'ship');
        $this->assertTrue($ok);
        $this->assertSame('SHIPPED', $this->do()->status);
        $this->assertNotNull($this->do()->actual_ship_date);
        $this->assertSame(['SHIPPED', 'SHIPPED'], CoOrderDelivery::whereIn('id_delivery', $this->sched)->orderBy('id_delivery')->pluck('status')->all());
        $this->assertSame(['SHIPPED', 'SHIPPED'], $this->lines()->pluck('status')->all());

        [$ok, $msg] = Data::act($this->do(), 'ship');
        $this->assertTrue($ok, 'Quirk v1: sudah SHIPPED dihitung ok.');
        $this->assertSame(t('The status did not change.'), $msg);

        [$ok, $msg] = Data::act($this->do(), 'cancel', 'salah');
        $this->assertFalse($ok);
        $this->assertSame(t('A SHIPPED/CANCELLED document cannot be cancelled.'), $msg);
    }

    public function test_ship_from_draft_is_refused_and_cancel_needs_a_reason_then_releases_the_qty(): void
    {
        $this->pullBoth();
        [$ok, $msg] = Data::act($this->do(), 'ship');
        $this->assertFalse($ok);
        $this->assertSame(t('The status did not change.'), $msg);
        $this->assertSame('DRAFT', $this->do()->status);

        Data::act($this->do(), 'confirm');
        $available = fn () => (float) DB::connection('tm2sdi')->table('v_co_delivery_outstanding')->where('co_delivery_id', $this->sched[0])->value('qty_available');
        $this->assertEqualsWithDelta(0.0, $available(), 0.01);

        [$ok, $msg] = Data::act($this->do(), 'cancel', '   ');
        $this->assertFalse($ok);
        $this->assertSame(t('The cancellation reason is required.'), $msg);

        [$ok] = Data::act($this->do(), 'cancel', 'ZZ-TEST salah alamat');
        $this->assertTrue($ok);
        $do = $this->do();
        $this->assertSame('CANCELLED', $do->status);
        $this->assertSame('ZZ-TEST salah alamat', $do->notes);
        $this->assertSame(['CANCELLED', 'CANCELLED'], $this->lines()->pluck('status')->all());
        $this->assertEqualsWithDelta(100.0, $available(), 0.01);
    }

    public function test_cancel_from_draft_is_allowed(): void
    {
        $this->pullBoth();
        [$ok] = Data::act($this->do(), 'cancel', 'ZZ-TEST batal draft');
        $this->assertTrue($ok);
        $this->assertSame('CANCELLED', $this->do()->status);
        $this->assertNull($this->do()->do_no);
    }
    public function test_confirm_refused_after_the_source_co_is_cancelled_returns_a_message_not_an_exception(): void
    {
        $this->pullBoth();
        DB::connection('tm2sdi')->table('co_order')->where('id_order', $this->coId)->update(['doc_status' => 'CANCELLED']);

        [$ok, $msg] = Data::act($this->do(), 'confirm');
        $this->assertFalse($ok);
        $this->assertStringContainsString('ZZ-TEST item A', $msg);
        $this->assertSame(tf('The source CO of line :item is no longer CONFIRMED.', ['item' => 'ZZ-TEST item A']), $msg);
        $this->assertSame('DRAFT', $this->do()->status);
        $this->assertNull($this->do()->do_no, 'Rollback: nomor DO tidak terbit.');
    }

    public function test_pull_copies_the_co_ship_address_and_confirm_requires_a_ship_to_address(): void
    {
        $this->pullBoth();
        $this->assertSame('ZZ-TEST Jl. Uji 1', $this->do()->ship_address_detail, 'Alamat kirim CO disalin ke DO saat baris ditarik.');

        // Alamat yang sudah dipilih pengguna tidak ditimpa tarik berikutnya.
        Data::save($this->do(), ['ship_address_detail' => 'ZZ-TEST Jl. Pilihan 2']);
        DoLine::where('delivery_id', $this->doId)->delete();
        $this->pullBoth();
        $this->assertSame('ZZ-TEST Jl. Pilihan 2', $this->do()->ship_address_detail);

        DoDelivery::whereKey($this->doId)->update(['ship_address_detail' => null, 'ship_address_label' => null]);
        [$ok, $msg] = Data::act($this->do(), 'confirm');
        $this->assertFalse($ok);
        $this->assertSame(t('The ship-to address is required before Confirm (Delivery tab).'), $msg);
        $this->assertSame('DRAFT', $this->do()->status);

        Data::save($this->do(), ['ship_address_detail' => 'ZZ-TEST Jl. Uji 3']);
        [$ok] = Data::act($this->do(), 'confirm');
        $this->assertTrue($ok);
    }

    public function test_delivery_note_cannot_be_printed_from_a_draft_or_cancelled_do(): void
    {
        $this->pullBoth();
        foreach (['DRAFT', 'CANCELLED'] as $status) {
            DoDelivery::whereKey($this->doId)->update(['status' => $status]);
            try {
                \App\Support\V2\Print\DeliveryNotePrint::load($this->doId);
                $this->fail('Surat Jalan dari DO '.$status.' harus ditolak.');
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
        }

        DoDelivery::whereKey($this->doId)->update(['status' => 'CONFIRMED']);
        $this->assertInstanceOf(\App\Support\V2\Print\DeliveryNotePrint::class, \App\Support\V2\Print\DeliveryNotePrint::load($this->doId));
    }

    public function test_confirm_ship_and_cancel_on_a_stale_object_are_judged_on_the_locked_row(): void
    {
        $this->pullBoth();
        $stale = $this->do(); // objek lama: masih DRAFT di memori
        [$ok] = Data::act($this->do(), 'confirm');
        $this->assertTrue($ok);
        $no = $this->do()->do_no;

        // Klik ganda Confirm: objek lama masih DRAFT, tetapi baris terkunci sudah CONFIRMED -> ditolak, nomor tidak berubah.
        $result = app(\App\Services\Dispatch\DispatchService::class)->confirm($stale, 'test');
        $this->assertFalse($result['ok']);
        $this->assertSame(t('The document no longer has status DRAFT.'), $result['message']);
        $this->assertSame($no, $this->do()->do_no);

        // Klik ganda Ship: objek lama CONFIRMED, baris terkunci sudah SHIPPED -> ditolak.
        $stale = $this->do();
        Data::act($this->do(), 'ship');
        $result = app(\App\Services\Dispatch\DispatchService::class)->markShipped($stale, 'test');
        $this->assertFalse($result['ok']);

        // Batal dari objek lama tidak boleh membatalkan DO yang sudah Shipped.
        $result = app(\App\Services\Dispatch\DispatchService::class)->cancel($stale, 'ZZ-TEST', 'test');
        $this->assertFalse($result['ok']);
        $this->assertSame('SHIPPED', $this->do()->status);
    }

    public function test_do_numbers_are_sequential_and_unique_per_year(): void
    {
        $this->pullBoth();
        Data::act($this->do(), 'confirm');
        $second = Data::create($this->customerId);
        try {
            DoDelivery::whereKey($second->id_delivery)->update(['ship_address_detail' => 'ZZ-TEST Jl. Uji 4']);
            DoLine::create(['delivery_id' => $second->id_delivery, 'line_no' => 1, 'item_description' => 'ZZ-TEST subkon', 'qty_do' => 1, 'qty_unit' => 'PCS', 'unit_price' => 0, 'status' => 'PLANNED', 'created_by' => 'test', 'updated_by' => 'test']);
            [$ok] = Data::act($second->refresh(), 'confirm');
            $this->assertTrue($ok);
            $first = (int) substr($this->do()->do_no, -5);
            $this->assertSame($first + 1, (int) substr($second->refresh()->do_no, -5));
        } finally {
            DoLine::where('delivery_id', $second->id_delivery)->delete();
            DoDelivery::whereKey($second->id_delivery)->delete();
        }
    }

    public function test_a_user_without_the_sales_price_right_cannot_change_line_prices(): void
    {
        $user = SecUser::where('is_admin', false)->get()->first(fn ($u) => ! $u->canViewSalesPrice());
        if (! $user) {
            $this->markTestSkipped('Tidak ada pengguna tanpa hak harga jual di database ini.');
        }

        $this->pullBoth();
        $line = $this->lines()[0];
        $this->actingAs($user);
        $this->assertSame('', Data::save($this->do(), ['lines' => [$line->id_line => ['unit_price' => '1']]]));
        $this->assertEqualsWithDelta(5000.0, (float) DoLine::find($line->id_line)->unit_price, 0.001);
        $this->assertFalse((bool) DoLine::find($line->id_line)->price_overridden);
    }

    public function test_pickup_do_keeps_the_customer_address_and_needs_who_vehicle_and_plate_before_confirm(): void
    {
        DB::connection('tm2sdi')->table('co_order')->where('id_order', $this->coId)->update(['shipment_method' => 'PICKUP']);
        $this->pullBoth();
        $this->assertSame('truck', $this->do()->pickup_vehicle, 'Jenis kendaraan bawaan truck');
        $this->assertSame('ZZ-TEST Jl. Uji 1', $this->do()->ship_address_detail, 'Alamat customer tetap disalin dari CO');
        $this->assertTrue($this->do()->isPickup());

        // Alamat tetap wajib, juga untuk Pickup.
        DoDelivery::whereKey($this->doId)->update(['ship_address_detail' => null, 'ship_address_label' => null]);
        [$ok, $msg] = Data::act($this->do(), 'confirm');
        $this->assertFalse($ok);
        $this->assertSame(t('The ship-to address is required before Confirm (Delivery tab).'), $msg);
        Data::save($this->do(), ['ship_address_detail' => 'ZZ-TEST Jl. Uji 1']);

        [$ok, $msg] = Data::act($this->do(), 'confirm');
        $this->assertFalse($ok);
        $this->assertSame(t('Pickup: fill in who picks up the goods (Delivery tab), for example Mr. XYZ.'), $msg);

        Data::save($this->do(), ['pickup_name' => 'Mr. XYZ', 'pickup_plate' => '']);
        [, $msg] = Data::act($this->do(), 'confirm');
        $this->assertSame(t('Pickup: fill in the vehicle plate number (Delivery tab), for example N 12345 SAMPLE.'), $msg);

        Data::save($this->do(), ['pickup_plate' => 'n  12345 sample']);
        $this->assertSame('N 12345 SAMPLE', $this->do()->pickup_plate, 'Huruf besar, satu spasi');
        Data::save($this->do(), ['pickup_plate' => 'N-12345']);
        [, $msg] = Data::act($this->do(), 'confirm');
        $this->assertSame(t('Pickup: the plate number may only contain letters, numbers and spaces.'), $msg);

        Data::save($this->do(), ['pickup_plate' => 'n 12345 sample']);
        $this->assertSame('PICKUP by Mr. XYZ by truck No. Polisi N 12345 SAMPLE', $this->do()->pickupNote());
        [$ok] = Data::act($this->do(), 'confirm');
        $this->assertTrue($ok);

        $lines = \App\Support\V2\Print\DeliveryNotePrint::load($this->doId)->data['party']['lines'];
        $this->assertSame('PICKUP by Mr. XYZ by truck No. Polisi N 12345 SAMPLE', end($lines));
        $this->assertContains('ZZ-TEST Jl. Uji 1', $lines, 'Alamat customer tidak dihapus');
    }

    public function test_a_delivery_do_has_no_pickup_fields_or_note(): void
    {
        $this->pullBoth();
        $this->assertFalse($this->do()->isPickup());
        $this->assertNull($this->do()->pickup_vehicle);
        $this->assertNull($this->do()->pickupNote());
        [$ok] = Data::act($this->do(), 'confirm');
        $this->assertTrue($ok, 'DO pengiriman biasa tidak butuh keterangan Pickup');
    }
}
