<?php

namespace Tests\Feature;

use App\Models\Inventory\InvQcDecision;
use App\Models\Inventory\InvShortageRequest;
use App\Services\Inventory\QcSamplingService;
use Tests\TestCase;

// Q-10: kejadian buruk supplier mengetatkan sampling: reject jenis apa pun (bukan hanya di dok) dan kurang kirim yang dikonfirmasi PPIC; yang ditutup
// sebagai varians pemakaian tidak dihitung; lot lulus yang dihitung pulih hanya yang sesudah kejadian. Supplier / item fiktif, dibersihkan sendiri.
class QcSamplingShortageTest extends TestCase
{
    private const SUPPLIER = 999999831;

    private const ITEM = 999999831;

    private const BY = 'test_qc_sampling_shortage';

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, InvQcDecision::query()->where('decided_by', self::BY)->count() + InvShortageRequest::query()->where('created_by', self::BY)->count(), 'Jejak uji masih ada.');
        parent::tearDown();
    }

    private function clean(): void
    {
        InvQcDecision::query()->where('decided_by', self::BY)->delete();
        InvShortageRequest::query()->where('created_by', self::BY)->delete();
    }

    private function decide(string $decision, int $times, \DateTimeInterface $at, string $type = 'SAMPLING_DOCK'): void
    {
        for ($i = 0; $i < $times; $i++) {
            InvQcDecision::query()->create(['batch_id' => 0, 'decision_type' => $type, 'decision' => $decision, 'status_after' => $decision, 'supplier_id' => self::SUPPLIER, 'decided_by' => self::BY, 'decided_at' => $at]);
        }
    }

    private function shortage(?string $outcome, \DateTimeInterface $confirmedAt): InvShortageRequest
    {
        return InvShortageRequest::query()->create(['request_no' => 'SRT'.random_int(10000, 99999), 'source_type' => 'SUPPLIER', 'supplier_id' => self::SUPPLIER, 'status' => 'CONFIRMED', 'outcome' => $outcome,
            'confirmed_at' => $confirmedAt, 'created_by' => self::BY, 'updated_by' => self::BY]);
    }

    private function intensity(): array
    {
        $p = QcSamplingService::planFor(self::ITEM, self::SUPPLIER, 100);

        return [$p['intensity'], $p['reason']];
    }

    public function test_a_confirmed_shortage_tightens_the_supplier_until_enough_lots_pass_after_it(): void
    {
        $this->decide('RELEASED', 10, now()->subDays(2));
        $this->assertSame(['REDUCED', 'CONSISTENT_SUPPLIER'], $this->intensity());

        $short = $this->shortage('USAGE_VARIANCE', now()->subDay());
        $this->assertSame('REDUCED', $this->intensity()[0], 'A shortage closed as usage variance is not the supplier\'s fault.');

        $short->update(['outcome' => null]);
        $this->assertSame(['TIGHTENED', 'RECOVERING'], $this->intensity(), 'The ten earlier passes do not count after the shortage.');

        $this->decide('RELEASED', 4, now());
        $this->assertSame('TIGHTENED', $this->intensity()[0]);
        $this->decide('RELEASED', 1, now());
        $this->assertSame('NORMAL', $this->intensity()[0], 'Five passed lots after the shortage end the tightened sampling.');
    }

    public function test_a_reject_of_any_decision_type_counts_against_the_supplier(): void
    {
        $this->decide('RELEASED', 10, now()->subDay());
        $this->assertSame('REDUCED', $this->intensity()[0]);

        $this->decide('REJECTED', 1, now(), 'FIRST_ARTICLE');
        $this->assertSame(['TIGHTENED', 'RECOVERING'], $this->intensity(), 'A first article reject at the machine tightens the dock sampling too.');
    }
}
