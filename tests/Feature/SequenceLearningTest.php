<?php

/*
 * REMARK
 * Tujuan       : Menguji ML urutan job: perekam (TRIAL ditimpa, FINAL saat APPLY, gagal tidak menggagalkan), pelatih (belajar aturan sintetis, gerbang 30 pasangan dan hold-out 20%), usulan (Use lewat queueMove, Dismiss = SEQ_REJECT), kolom Suggested hanya baris Draft.
 * Sumber hulu  : App\Services\Scheduling\Sequence, prd_plan_trace, prd_sequence_model, prd_plan_decision.
 * Input        : data dummy ddt* (WO Draft) + baris sintetis; semuanya di transaksi yang di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test --env=mockup (skip di tm2* atau bila tabel belum dimigrasi).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Prd\PrdPlanDecision;
use App\Models\Prd\PrdPlanTrace;
use App\Models\Prd\PrdSequenceModel;
use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\PlanChangeService;
use App\Services\Scheduling\Sequence\FeatureSet;
use App\Services\Scheduling\Sequence\SequenceSuggester;
use App\Services\Scheduling\Sequence\SequenceTrainer;
use App\Services\Scheduling\Sequence\TraceRecorder;
use App\Support\V2\Data\Ppic;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SequenceLearningTest extends TestCase
{
    private $prd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prd = DB::connection('tm2prd');
        if (! str_starts_with($this->prd->getDatabaseName(), 'ddt') || ! Schema::connection('tm2prd')->hasTable('prd_sequence_model')) {
            $this->markTestSkipped('Dummy database with the sequence tables only.');
        }
        $this->prd->beginTransaction();
        PrdSequenceModel::query()->delete();
        PrdPlanTrace::query()->delete();
        Cache::flush();
        Ppic::forget();
    }

    protected function tearDown(): void
    {
        $this->prd?->rollBack();
        Ppic::forget();
        Cache::flush();
        parent::tearDown();
    }

    /**
     * $n snapshots of 4 jobs. The officer always puts the more urgent job first (urgency feature descending). $engineAgrees = the engine order
     * equals the officer order (then the model can never beat it); otherwise the engine order is the reverse of the officer order.
     */
    private function synthetic(int $n, bool $engineAgrees, bool $fromSuggestion = false): void
    {
        $names = FeatureSet::names();
        $wo = (int) $this->prd->table('prd_wo')->value('wo_id');
        mt_srand(7);
        for ($s = 0; $s < $n; $s++) {
            $vals = [];
            for ($j = 0; $j < 4; $j++) {
                $vals[] = round(mt_rand(-200, 200) / 100, 2);
            }
            arsort($vals);                      // most urgent first
            $pos = 0;
            foreach ($vals as $u) {
                $f = array_fill_keys($names, 0.0);
                $f['urgency'] = $u;
                $f['duration'] = round(mt_rand(0, 100) / 100, 2);
                PrdPlanTrace::create(['snapshot_wo_id' => $wo, 'snapshot_key' => 'S'.$s, 'wo_id' => $wo, 'wo_operation_id' => 900000 + $s * 10 + $pos, 'machine_id' => 1, 'status' => 'FINAL',
                    'engine_pos' => $engineAgrees ? $pos : 3 - $pos, 'officer_pos' => $pos, 'features' => $f, 'from_suggestion' => $fromSuggestion, 'created_at' => now()->addMinutes($s), 'updated_at' => now()]);
                $pos++;
            }
        }
    }

    public function test_learns_a_known_rule_and_passes_the_gate(): void
    {
        $this->synthetic(10, false);                 // 10 x 6 = 60 pairs
        $m = SequenceTrainer::run();

        $this->assertSame('ACTIVE', $m->status);
        $this->assertGreaterThanOrEqual(30, $m->pairs_count);
        $this->assertGreaterThan((float) $m->holdout_accuracy_engine, (float) $m->holdout_accuracy_model);
        $w = $m->weights['w'];
        $this->assertGreaterThan(0.5, $w['urgency'], 'the more urgent job must score higher');
        $this->assertSame('ACTIVE', SequenceSuggester::active()->status);

        // idempotent: nothing new = no new version
        $this->assertNull(SequenceTrainer::run());
    }

    public function test_below_thirty_pairs_there_is_no_suggestion(): void
    {
        $this->synthetic(3, false);                  // 18 pairs
        $m = SequenceTrainer::run();

        $this->assertSame('CANDIDATE', $m->status);
        $this->assertNull(SequenceSuggester::active());
        $st = SequenceSuggester::status();
        $this->assertSame(18, $st['pairs']);
        $this->assertSame(30, $st['needed']);
    }

    public function test_a_model_that_is_not_better_than_the_engine_stays_a_candidate(): void
    {
        $this->synthetic(10, true);                  // the engine order already equals the officer order: accuracy 100%
        $m = SequenceTrainer::run();

        $this->assertSame('CANDIDATE', $m->status);
        $this->assertNull(SequenceSuggester::active());
    }

    public function test_pairs_from_a_suggestion_are_not_used(): void
    {
        $this->synthetic(10, false, true);
        $this->assertSame(0, SequenceTrainer::counts()['pairs']);
        $this->assertSame('CANDIDATE', SequenceTrainer::run()->status);
    }

    public function test_trial_is_replaced_and_final_is_recorded_and_failure_never_throws(): void
    {
        app(AutoPlanRunner::class)->run('EVENT', 'test');
        Ppic::forget();
        $op = PrdWoOperation::where('plan_status', 'DRAFT')->where('draft_machine_id', '>', 0)
            ->whereHas('wo', fn ($q) => $q->whereIn('wo_status', ['DRAFT', 'SIMULATED']))->orderBy('operation_id')->first();
        $this->assertNotNull($op, 'no Draft operation');
        $wo = (int) $op->wo_id;

        TraceRecorder::recordTrial($wo);
        $first = PrdPlanTrace::where('snapshot_wo_id', $wo)->where('status', 'TRIAL')->count();
        $this->assertGreaterThan(0, $first);
        TraceRecorder::recordTrial($wo);
        $this->assertSame($first, PrdPlanTrace::where('snapshot_wo_id', $wo)->where('status', 'TRIAL')->count(), 'TRIAL must be replaced, not stacked');
        $row = PrdPlanTrace::where('snapshot_wo_id', $wo)->first();
        $this->assertCount(count(FeatureSet::names()), (array) $row->features);

        TraceRecorder::recordFinal($wo, 424242);
        $this->assertSame(0, PrdPlanTrace::where('snapshot_wo_id', $wo)->where('status', 'TRIAL')->count());
        $this->assertGreaterThan(0, PrdPlanTrace::where('status', 'FINAL')->where('run_id', 424242)->count());

        // a failing recorder is swallowed
        TraceRecorder::recordTrial(-1);
        TraceRecorder::recordFinal(-1, 1);
        $this->assertTrue(true);
    }

    public function test_use_dismiss_and_the_suggested_column(): void
    {
        app(AutoPlanRunner::class)->run('EVENT', 'test');
        Ppic::forget();
        $byMachine = collect(Ppic::operations())->filter(fn ($o) => $o['plan_status'] === 'DRAFT' && ! $o['locked'] && $o['machine_id'] && $o['start'])->groupBy('machine_id')
            ->filter(fn ($g) => $g->count() >= 2);
        if ($byMachine->isEmpty()) {
            $this->markTestSkipped('No machine with two movable Draft operations in the dummy data.');
        }
        $machine = (int) $byMachine->keys()->first();
        // an ACTIVE model that puts the LEAST urgent job first (the reverse of urgency), so the suggestion differs from the current order
        PrdSequenceModel::create(['version' => 1, 'status' => 'ACTIVE', 'weights' => ['w' => array_merge(array_fill_keys(FeatureSet::names(), 0.0), ['urgency' => -1.0, 'duration' => 0.01]), 'sig' => 'test'],
            'feature_names' => FeatureSet::names(), 'pairs_count' => 40, 'arrangements_count' => 8, 'holdout_accuracy_model' => 0.9, 'holdout_accuracy_engine' => 0.6, 'trained_at' => now()]);
        Cache::flush();

        $s = SequenceSuggester::suggest($machine);
        $this->assertNotNull($s);
        $this->assertCount(count($s['current']), $s['order']);

        // QUE: the Suggested column exists, only Draft rows carry a value
        $q = Ppic::queue();
        $this->assertTrue($q['suggested']);
        foreach ($q['rows'] as $r) {
            if ($r['status'] !== 'Draft') {
                $this->assertArrayNotHasKey('suggested', $r);
            }
        }

        $res = SequenceSuggester::use($machine, app(PlanChangeService::class), 'test');
        $this->assertArrayHasKey('moved', $res);
        $this->assertTrue(PrdPlanDecision::where('event', 'SEQ_USE')->where('old_value', 'm'.$machine)->exists());

        Cache::flush();
        Ppic::forget();
        $this->assertTrue(SequenceSuggester::dismiss($machine, 'test'));
        $this->assertTrue(PrdPlanDecision::where('event', 'SEQ_REJECT')->where('old_value', 'm'.$machine)->exists());
    }
}
