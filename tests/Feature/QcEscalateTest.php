<?php

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\QcAuthority;
use App\Services\Inventory\QcEscalationService;
use App\Services\Inventory\QcNotifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Eskalasi keputusan mutu (D16): inv:qc-escalate memberi tahu kepala QC / Ops Manager (lewat INV_QC_ESCALATE_HOURS) dan menambah QA + PPIC (lewat
// INV_QC_DEADLINE_HOURS), satu pesan per batch per tahap. Jalankan lewat tools/test-ddt.ps1; batch uji dan pemberitahuannya dibersihkan sendiri.
class QcEscalateTest extends TestCase
{
    private const BY = 'test_qc_escalate';

    private int $materialId;

    private string $startedAt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startedAt = now()->subSeconds(2)->toDateTimeString();
        $this->materialId = (int) DB::connection('tm2mst')->table('mat_raw')->where('activate', 1)->orderBy('material_id')->value('material_id');
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, InvBatch::query()->where('created_by', self::BY)->count() + DB::connection('tm2sys')->table('sys_notifications')->where('message', 'like', 'TQE-%')->count(), 'Jejak uji masih ada.');
        parent::tearDown();
    }

    private function clean(): void
    {
        // inv:qc-escalate menjangkau juga batch Conditional lain di database dummy: pemberitahuan yang lahir selama uji ikut dibersihkan.
        DB::connection('tm2sys')->table('sys_notifications')->whereIn('title', ['Quality decision escalated', 'Quality decision overdue'])->where('created_at', '>=', $this->startedAt)->delete();
        DB::connection('tm2sys')->table('sys_notifications')->where('message', 'like', 'TQE-%')->delete();
        InvBatch::query()->where('created_by', self::BY)->delete();
    }

    private function batch(string $lot, string $status, int $hoursAgo): InvBatch
    {
        $batch = BatchService::create('RM', $this->materialId, $lot, ['qc_status' => $status], self::BY);
        $batch->forceFill(['qc_status_at' => now()->subHours($hoursAgo), 'batch_no' => 'TQE-'.$lot])->save();

        return $batch;
    }

    private function noticesFor(string $batchNo, string $title): int
    {
        return DB::connection('tm2sys')->table('sys_notifications')->where('message', 'like', $batchNo.' %')->where('title', $title)->count();
    }

    public function test_batches_past_the_limits_notify_the_right_groups_once_per_stage(): void
    {
        $this->batch('A', 'CONDITIONAL', 1);
        $this->batch('B', 'CONDITIONAL', 5);
        $this->batch('C', 'QUARANTINE', 20);
        $this->batch('D', 'RELEASED', 99);

        $dry = QcEscalationService::run(true);
        $mine = collect($dry)->whereIn('batch_no', ['TQE-A', 'TQE-B', 'TQE-C', 'TQE-D'])->pluck('state', 'batch_no')->all();
        ksort($mine);
        $this->assertSame(['TQE-B' => 'ESCALATE', 'TQE-C' => 'OVERDUE'], $mine, 'Only Conditional / Quarantine batches past the limits are listed.');
        $this->assertSame(0, $this->noticesFor('TQE-B', 'Quality decision escalated'), 'A dry run sends nothing.');

        $this->assertSame(0, Artisan::call('inv:qc-escalate'));
        $escalate = count(QcNotifier::recipients(['QA_HEAD', 'OPS_MGR']));
        $overdue = count(QcNotifier::recipients(['QA_HEAD', 'QA', 'OPS_MGR', 'PROD_PLANNING']));
        $this->assertSame($escalate, $this->noticesFor('TQE-B', 'Quality decision escalated'));
        $this->assertSame($overdue, $this->noticesFor('TQE-C', 'Quality decision overdue'));
        $this->assertSame(0, $this->noticesFor('TQE-A', 'Quality decision escalated'));
        $this->assertGreaterThan(0, $escalate, 'OPS_MGR has a user in the test database.');

        Artisan::call('inv:qc-escalate');
        $this->assertSame($escalate, $this->noticesFor('TQE-B', 'Quality decision escalated'), 'A second run adds no duplicate.');

        // B melewati tenggat: pesan tahap berikutnya, bukan pengulangan.
        InvBatch::query()->where('batch_no', 'TQE-B')->update(['qc_status_at' => now()->subHours(9)]);
        Artisan::call('inv:qc-escalate');
        $this->assertSame($overdue, $this->noticesFor('TQE-B', 'Quality decision overdue'));
        $this->assertContains('QA_HEAD', QcAuthority::URGENT_APPROVE, 'The head of QC is the escalation target.');
    }
}
