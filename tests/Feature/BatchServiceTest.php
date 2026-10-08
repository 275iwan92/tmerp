<?php

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Services\Inventory\BatchService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

// Master batch v2.01: nomor otomatis, satu batch per item + lot, bendera status. Menulis ke database yang dipakai uji (jalankan lewat
// tools/test-ddt.ps1 supaya ke ddt*) dengan item fiktif; membersihkan sendiri di tearDown().
class BatchServiceTest extends TestCase
{
    private const BY = 'test_batch_service';

    private const ITEM = 999999701;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, DB::connection('tm2inv')->table('inv_batch')->where('created_by', self::BY)->count(), 'inv_batch masih berisi baris uji.');
        parent::tearDown();
    }

    private function clean(): void
    {
        DB::connection('tm2inv')->table('inv_batch')->where('created_by', self::BY)->delete();
    }

    public function test_batch_numbers_follow_the_pattern_and_lot_is_unique_per_item_ignoring_case_and_spaces(): void
    {
        $a = BatchService::ensure('RM', self::ITEM, ' mill-9921 ', ['expiry_date' => '2099-12-31', 'certification' => 'FSC'], self::BY);
        $again = BatchService::ensure('RM', self::ITEM, 'MILL-9921', ['expiry_date' => '2030-01-01'], self::BY);

        $this->assertMatchesRegularExpression('/^BT\d{2}\d{5}$/', $a->batch_no);
        $this->assertSame($a->id_batch, $again->id_batch, 'Same item and lot must give the same batch.');
        $this->assertSame('mill-9921', $a->supplier_lot);
        $this->assertSame('2099-12-31', $a->expiry_date->toDateString(), 'Attributes are used only when the batch is created.');
        $this->assertSame('RELEASED', $a->qc_status);

        $other = BatchService::ensure('RM', self::ITEM, 'MILL-9922', [], self::BY);
        $this->assertNotSame($a->batch_no, $other->batch_no);
        $this->assertGreaterThan($a->batch_no, $other->batch_no);
    }

    public function test_empty_lot_becomes_one_no_lot_batch_per_item(): void
    {
        $a = BatchService::ensure('RM', self::ITEM, null, [], self::BY);
        $b = BatchService::ensure('RM', self::ITEM, '   ', [], self::BY);

        $this->assertSame($a->id_batch, $b->id_batch);
        $this->assertNull($a->supplier_lot);
        $this->assertSame($a->id_batch, BatchService::findByLot('RM', self::ITEM, 'no-lot')->id_batch);
    }

    public function test_status_flags_decide_available_and_allocatable_and_rejection_needs_a_reason(): void
    {
        $this->assertSame([false, false], [BatchService::flags('QUARANTINE')['available'], BatchService::flags('QUARANTINE')['allocatable']]);
        $this->assertSame([true, true], [BatchService::flags('CONDITIONAL')['available'], BatchService::flags('CONDITIONAL')['allocatable']]);
        $this->assertSame([true, true], [BatchService::flags('RELEASED')['available'], BatchService::flags('RELEASED')['allocatable']]);
        $this->assertSame([false, false], [BatchService::flags('REJECTED')['available'], BatchService::flags('REJECTED')['allocatable']]);

        $batch = BatchService::create('RM', self::ITEM, 'LOT-X', ['qc_status' => 'CONDITIONAL'], self::BY);
        $this->assertTrue($batch->isAllocatable());
        $this->assertTrue(BatchService::allocatable(null), 'Stock without a batch (old data) stays usable.');

        $released = BatchService::setStatus($batch, 'RELEASED', self::BY);
        $this->assertSame('RELEASED', $released->fresh()->qc_status);

        try {
            BatchService::setStatus($batch, 'REJECTED', self::BY);
            $this->fail('A rejection without a reason must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Alasan', $e->getMessage());
        }
        $rejected = BatchService::setStatus($batch, 'REJECTED', self::BY, 'Colour off against the standard');
        $this->assertFalse($rejected->fresh()->isAllocatable());
        $this->assertFalse(BatchService::allocatable($rejected->fresh()));
    }

    public function test_unknown_status_or_class_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        BatchService::create('XX', self::ITEM, 'LOT', [], self::BY);
    }

    public function test_expiry_state_uses_the_soon_days_limit(): void
    {
        $soon = new InvBatch(['expiry_date' => now()->addDays(10)->toDateString()]);
        $late = new InvBatch(['expiry_date' => now()->subDay()->toDateString()]);
        $none = new InvBatch;

        $this->assertSame('SOON', $soon->expiryState(30));
        $this->assertNull($soon->expiryState(5));
        $this->assertSame('EXPIRED', $late->expiryState(30));
        $this->assertNull($none->expiryState(30));
    }
}
