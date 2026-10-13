<?php

/*
 * REMARK
 * Tujuan       : Menguji kolom Start dan Stop yang bisa diedit di sheet Bill of Process: Start menggeser operasi lewat engine dampak yang sama dengan bar Gantt (pending), Stop mengubah target per jam, nilai tak sah ditolak.
 * Sumber hulu  : PpicController::targetCells / applyTimes, PlanChangeService::queueMove, Ppic::bopSheet.
 * Input        : WO DRAFT di database dummy dengan operasi bermesin dan jadwal; semuanya di transaksi yang di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test (skip di tm2*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Prd\PrdWo;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\Ppic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WoBopTimesTest extends TestCase
{
    private $prd;

    private array $row;

    private PrdWo $wo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prd = DB::connection('tm2prd');
        if (! str_starts_with($this->prd->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Dummy database only.');
        }
        $this->actingAs(SecUser::where('is_admin', 1)->firstOrFail());
        $this->prd->beginTransaction();
        Ppic::forget();
        foreach (PrdWo::where('wo_status', 'DRAFT')->whereHas('operations')->orderBy('wo_id')->get() as $wo) {
            $row = collect(Ppic::bopSheet($wo->wo_id))->first(fn ($r) => ctype_digit((string) $r['machine']) && $r['start'] !== '' && $r['finish'] !== '' && $r['target']);
            if ($row) {
                [$this->wo, $this->row] = [$wo, $row];

                return;
            }
        }
        $this->markTestSkipped('No Draft Work Order with a scheduled machine operation.');
    }

    protected function tearDown(): void
    {
        $this->prd?->rollBack();
        Ppic::forget();
        parent::tearDown();
    }

    private function save(array $cells)
    {
        return $this->postJson(route('v2.ppic.wo.target-cells', $this->wo->wo_id), ['changed' => [['id' => $this->row['id']] + $cells]]);
    }

    private function now(): array
    {
        Ppic::forget();

        return collect(Ppic::bopSheet($this->wo->wo_id))->firstWhere('id', $this->row['id']);
    }

    public function test_sheet_sends_start_and_stop_as_iso_text(): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $this->row['start']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $this->row['finish']);
    }

    public function test_stop_later_lowers_the_target_per_hour(): void
    {
        $stop = CarbonImmutable::parse($this->row['finish'])->addHours(2)->format('Y-m-d H:i:s');
        $this->save(['finish' => $stop])->assertOk();

        $this->assertLessThan((float) $this->row['target'], (float) $this->now()['target']);
    }

    public function test_stop_before_the_run_can_start_is_refused(): void
    {
        $stop = CarbonImmutable::parse($this->row['start'])->subDay()->format('Y-m-d H:i:s');
        $this->save(['finish' => $stop])->assertStatus(422);
        $this->assertSame((float) $this->row['target'], (float) $this->now()['target']);
    }

    public function test_start_later_moves_the_operation_as_a_pending_change(): void
    {
        $start = CarbonImmutable::parse($this->row['start'])->addDays(2)->format('Y-m-d H:i:s');
        $res = $this->save(['start' => $start]);
        if ($res->status() === 422) {
            $this->assertNotSame('', (string) $res->json('error'), 'a refused move says why');

            return;
        }
        $res->assertOk();
        $this->assertTrue((bool) $this->prd->table('prd_wo_operation')->where('operation_id', $this->row['id'])->value('pending_move'));
        $this->assertGreaterThan($this->row['start'], $this->now()['start']);
    }

    public function test_unchanged_start_and_stop_change_nothing(): void
    {
        $this->save(['start' => $this->row['start'], 'finish' => $this->row['finish']])->assertOk()->assertJson(['saved' => 0]);
    }
}
