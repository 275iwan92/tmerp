<?php

/*
 * REMARK
 * Tujuan       : Menjaga definisi Available PPIC (Jo 13 Okt 2026): bebas dari booking / reserve, termasuk booking Draft / PRE; tidak terhitung dua kali; minus jadi 0.
 * Sumber hulu  : AvailableStock::of (tm2inv.v_rm_available.qty_onhand dikurangi sisa rm_booking_d terbuka semua tahap), Ppic::stockOf, GateService.
 * Input        : satu bahan berstok di database dummy; baris booking sintetis di transaksi yang di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test (skip di tm2*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Services\Scheduling\Plan\AvailableStock;
use App\Support\V2\Data\Ppic;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WoAvailableTest extends TestCase
{
    private $inv;

    private int $material;

    private float $onhand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inv = DB::connection('tm2inv');
        if (! str_starts_with($this->inv->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Dummy database only.');
        }
        $r = $this->inv->table('v_rm_available')->where('is_usable', 1)->selectRaw('material_id, SUM(qty_onhand) oh')->groupBy('material_id')
            ->havingRaw('SUM(qty_onhand) >= 20')->orderBy('material_id')->first();
        if (! $r) {
            $this->markTestSkipped('No stocked material.');
        }
        $this->material = (int) $r->material_id;
        $this->inv->beginTransaction();
        // start from a clean slate for this material: no booking at all, so each case below adds exactly its own
        $this->inv->table('rm_booking_d')->where('material_id', $this->material)->delete();
        $this->onhand = AvailableStock::of([$this->material])[$this->material]['onhand'];
    }

    protected function tearDown(): void
    {
        $this->inv?->rollBack();
        parent::tearDown();
    }

    /** One booking (header + one line) of this material in the given state. */
    private function booking(float $book, array $head = [], array $line = []): void
    {
        $id = $this->inv->table('rm_booking')->insertGetId($head + ['booking_no' => 'T'.uniqid(), 'booking_date' => now(), 'booking_type' => 'CO', 'doc_status' => 'DRAFT',
            'stat_confirm' => 0, 'stat_cancel' => 0, 'stat_closed' => 0]);
        $this->inv->table('rm_booking_d')->insert($line + ['booking_id' => $id, 'seq_no' => 1, 'material_id' => $this->material, 'qty_book' => $book, 'qty_issued' => 0, 'stat_cancel' => 0]);
    }

    private function available(): float
    {
        return AvailableStock::of([$this->material])[$this->material]['available'];
    }

    public function test_draft_and_pre_bookings_lower_available(): void
    {
        $this->assertEqualsWithDelta($this->onhand, $this->available(), 0.0001);

        $this->booking(5, ['doc_status' => 'DRAFT']);                                  // B2 draft of a CO
        $this->assertEqualsWithDelta($this->onhand - 5, $this->available(), 0.0001);

        $this->booking(3, ['booking_type' => 'PRE', 'doc_status' => 'DRAFT', 'stage' => 'B0']);   // pre-booking
        $this->assertEqualsWithDelta($this->onhand - 8, $this->available(), 0.0001);
        $this->assertEqualsWithDelta($this->onhand, Ppic::stockOf([$this->material])[$this->material]['onhand'], 0.0001, 'On hand does not move');
        $this->assertEqualsWithDelta($this->onhand - 8, Ppic::stockOf([$this->material])[$this->material]['available'], 0.0001, 'the BOM sheet uses the same number');
    }

    public function test_a_confirmed_booking_is_counted_once(): void
    {
        $this->booking(4, ['stat_confirm' => 1, 'doc_status' => 'CONFIRMED']);
        $this->assertEqualsWithDelta($this->onhand - 4, $this->available(), 0.0001, 'the view already counts it; PPIC must not subtract it again');
    }

    public function test_cancelled_closed_and_issued_do_not_lower_available(): void
    {
        $this->booking(6, ['stat_cancel' => 1, 'doc_status' => 'CANCELLED']);          // header cancelled
        $this->booking(6, ['stat_closed' => 1, 'stat_confirm' => 1]);                   // header closed
        $this->booking(6, [], ['stat_cancel' => 1]);                                    // line cancelled
        $this->booking(6, [], ['qty_issued' => 6]);                                     // fully issued: nothing left
        $this->assertEqualsWithDelta($this->onhand, $this->available(), 0.0001);

        $this->booking(6, [], ['qty_issued' => 2]);                                     // partly issued: 4 left
        $this->assertEqualsWithDelta($this->onhand - 4, $this->available(), 0.0001);
    }

    public function test_an_expired_hold_does_not_count_and_a_live_hold_does(): void
    {
        $this->booking(5, ['doc_status' => 'HELD', 'stat_confirm' => 1, 'hold_until' => now()->subDays(2)->toDateString()]);
        $this->assertEqualsWithDelta($this->onhand, $this->available(), 0.0001, 'the hold ended by itself');

        $this->booking(5, ['doc_status' => 'HELD', 'stat_confirm' => 1, 'hold_until' => now()->addDays(2)->toDateString()]);
        $this->assertEqualsWithDelta($this->onhand - 5, $this->available(), 0.0001);
    }

    public function test_available_never_goes_below_zero(): void
    {
        $this->booking($this->onhand + 50);
        $this->assertSame(0.0, $this->available());
        $this->assertEqualsWithDelta($this->onhand, Ppic::stockOf([$this->material])[$this->material]['onhand'], 0.0001);
    }
}
