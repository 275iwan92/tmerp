<?php

namespace Tests\Feature;

use App\Models\Inventory\InvQcDecision;
use App\Models\Inventory\InvQcRiskClass;
use App\Services\Inventory\QcSamplingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Rencana sampling QC (Q-01, Q-10): kelas risiko, intensitas menurut riwayat supplier, ukuran sampel menurut jumlah lot.
// Jalankan lewat tools/test-ddt.ps1 (ke ddt*). Memakai supplier / item fiktif dan membersihkan sendiri di tearDown().
class QcSamplingTest extends TestCase
{
    private const SUPPLIER = 999999811;

    private const ITEM = 999999811;

    private const BY = 'test_qc_sampling';

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, InvQcDecision::query()->where('decided_by', self::BY)->count(), 'inv_qc_decision masih berisi baris uji.');
        $this->assertSame(0, InvQcRiskClass::query()->where('created_by', self::BY)->count(), 'inv_qc_risk_class masih berisi baris uji.');
        parent::tearDown();
    }

    private function clean(): void
    {
        InvQcDecision::query()->where('decided_by', self::BY)->delete();
        InvQcRiskClass::query()->where('created_by', self::BY)->delete();
    }

    private function decide(string $decision, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            InvQcDecision::query()->create(['batch_id' => 0, 'decision_type' => 'SAMPLING_DOCK', 'decision' => $decision, 'status_after' => $decision, 'supplier_id' => self::SUPPLIER,
                'decided_by' => self::BY, 'decided_at' => now()]);
        }
    }

    public function test_item_without_class_is_b_and_a_new_supplier_is_sampled_tightened(): void
    {
        $plan = QcSamplingService::planFor(self::ITEM, self::SUPPLIER, 100);

        $this->assertSame('B', $plan['risk_class']);
        $this->assertSame('TIGHTENED', $plan['intensity']);
        $this->assertSame('NEW_SUPPLIER', $plan['reason']);
        $this->assertSame(30, $plan['sample_size'], 'Normal 20 for 91-150, tightened = 1.5 x.');
    }

    public function test_intensity_follows_the_supplier_history(): void
    {
        $this->decide('RELEASED', 5);
        $normal = QcSamplingService::planFor(self::ITEM, self::SUPPLIER, 100);
        $this->assertSame(['NORMAL', 20], [$normal['intensity'], $normal['sample_size']]);

        $this->decide('RELEASED', 5);
        $reduced = QcSamplingService::planFor(self::ITEM, self::SUPPLIER, 100);
        $this->assertSame(['REDUCED', 8], [$reduced['intensity'], $reduced['sample_size']]);

        $this->decide('REJECTED');
        $again = QcSamplingService::planFor(self::ITEM, self::SUPPLIER, 100);
        $this->assertSame(['TIGHTENED', 'RECOVERING'], [$again['intensity'], $again['reason']], 'One reject sends the supplier back to tightened.');

        $this->decide('RELEASED', 5);
        $recovered = QcSamplingService::planFor(self::ITEM, self::SUPPLIER, 100);
        $this->assertSame('NORMAL', $recovered['intensity'], 'Five passed lots after the reject end the tightened sampling.');
    }

    public function test_class_c_item_is_skipped_and_the_sample_never_exceeds_the_lot(): void
    {
        InvQcRiskClass::query()->create(['scope_key' => 'I:'.self::ITEM, 'scope_type' => 'ITEM', 'material_id' => self::ITEM, 'risk_class' => 'C', 'created_by' => self::BY, 'updated_by' => self::BY]);
        $this->decide('RELEASED', 5);

        $plan = QcSamplingService::planFor(self::ITEM, self::SUPPLIER, 100);
        $this->assertSame(['C', 'SKIP', 0], [$plan['risk_class'], $plan['intensity'], $plan['sample_size']]);

        $this->assertSame(0, QcSamplingService::sampleSize('SKIP', 500));
        $this->assertSame(2, QcSamplingService::sampleSize('NORMAL', 2), 'A lot of 2 is sampled at most 2.');
        $this->assertSame(1, QcSamplingService::sampleSize('NORMAL', 1));
        $this->assertSame(1250, QcSamplingService::sampleSize('NORMAL', 900000), 'The open ended last range applies above 500001.');
    }

    public function test_history_ignores_other_suppliers_and_other_decision_types(): void
    {
        DB::connection('tm2inv')->table('inv_qc_decision')->insert(['batch_id' => 0, 'decision_type' => 'USAGE_DECISION', 'decision' => 'REJECTED', 'status_after' => 'REJECTED',
            'supplier_id' => self::SUPPLIER, 'decided_by' => self::BY, 'decided_at' => now()]);
        $history = QcSamplingService::history(self::SUPPLIER);

        $this->assertSame(0, $history['lots'], 'Only the dock sampling counts as a lot for the supplier history.');
        $this->assertSame(0, QcSamplingService::history(null)['lots']);
    }
}
