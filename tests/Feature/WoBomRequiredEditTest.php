<?php

/*
 * REMARK
 * Tujuan       : Menguji edit Required/Release/Purchase pada tab Bill of Material (rumus, Reason, WO berjalan ditolak).
 * Sumber hulu  : PpicController::bomCells, prd_wo_material.
 * Input        : WO DRAFT pertama yang punya baris bahan; transaksi di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test (DB_* diarahkan ke ddt*).
 * Status       : Aktif; skip bila kolom belum dimigrasi.
 */

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Services\Scheduling\Plan\GateService;
use App\Support\V2\Data\Ppic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Bill of Material tab of the Work Order form: Release = Available, Purchase = max(0, Required - Release), edits need one reason when they differ from the
 * engine, the engine number stays in qty_standar, a Work Order that is already running refuses. Runs inside a rolled-back transaction.
 */
class WoBomRequiredEditTest extends TestCase
{
    private $prd;

    private $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prd = DB::connection('tm2prd');
        if (! Schema::connection('tm2prd')->hasColumn('prd_wo_material', 'qty_release')) {
            $this->markTestSkipped('prd_wo_material.qty_release is not migrated in this database.');
        }
        $this->line = $this->prd->table('prd_wo_material as m')->join('prd_wo as w', 'w.wo_id', '=', 'm.wo_id')
            ->where('w.wo_status', 'DRAFT')->where('m.qty_standar', '>', 0)->orderBy('m.detail_id')->first(['m.detail_id', 'm.wo_id', 'm.qty_standar']);
        if (! $this->line) {
            $this->markTestSkipped('No Draft Work Order with a material line.');
        }
        $this->actingAs(SecUser::where('is_admin', 1)->firstOrFail());
        $this->prd->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->prd?->rollBack();
        parent::tearDown();
    }

    private function edit(array $row, ?string $reason = null)
    {
        return $this->postJson(route('v2.ppic.wo.bom-cells', $this->line->wo_id), ['changed' => [['id' => $this->line->detail_id] + $row], 'reason' => $reason]);
    }

    private function stored()
    {
        return $this->prd->table('prd_wo_material')->where('detail_id', $this->line->detail_id)->first();
    }

    /** Available of the test line as the sheet shows it (the Release default). */
    private function available(): float
    {
        return (float) collect(Ppic::bomSheet($this->line->wo_id))->firstWhere('id', (int) $this->line->detail_id)['avail_qty'];
    }

    public function test_release_is_available_by_default_and_purchase_is_required_minus_release(): void
    {
        $row = collect(Ppic::bomSheet($this->line->wo_id))->firstWhere('id', (int) $this->line->detail_id);
        $req = (float) $row['required'];
        $this->assertEqualsWithDelta($this->available(), (float) $row['release'], 0.005, 'Release (untouched) = Available');
        $this->assertEqualsWithDelta(max(0.0, $req - (float) $row['release']), (float) $row['purchase'], 0.005, 'Purchase = Required - Release, never below 0');
    }

    public function test_four_cases_of_available_against_required(): void
    {
        $g = GateService::class;
        // Available 0 / "—": Release 0, buy everything
        $this->assertSame([0.0, 100.0], [$g::release(null, 0.0), $g::purchase(100.0, $g::release(null, 0.0))]);
        // Available below Required: Release = Available, buy the rest
        $this->assertSame([40.0, 60.0], [$g::release(null, 40.0), $g::purchase(100.0, $g::release(null, 40.0))]);
        // Available = Required: nothing to buy
        $this->assertSame([100.0, 0.0], [$g::release(null, 100.0), $g::purchase(100.0, $g::release(null, 100.0))]);
        // Available above Required: Release = Available, Purchase shows 0 (not minus)
        $this->assertSame([250.0, 0.0], [$g::release(null, 250.0), $g::purchase(100.0, $g::release(null, 250.0))]);
        // an officer value wins over Available
        $this->assertSame([30.0, 70.0], [$g::release(30.0, 250.0), $g::purchase(100.0, $g::release(30.0, 250.0))]);
    }

    public function test_release_above_available_is_refused_and_a_lower_release_needs_a_reason(): void
    {
        $req = (float) $this->line->qty_standar;
        $avail = $this->available();

        $this->edit(['required' => $req, 'release' => $avail + 1], 'x')->assertStatus(422);     // Release above Available

        if ($avail > 0) {
            $this->edit(['required' => $req, 'release' => $avail - 0.5])->assertStatus(422);       // differs from the default, no reason
            $this->edit(['required' => $req, 'release' => $avail - 0.5], 'Keep some for the next job')->assertOk();
            $m = $this->stored();
            $this->assertEqualsWithDelta($avail - 0.5, (float) $m->qty_release, 0.0001);
            $this->assertEqualsWithDelta(max(0.0, $req - ($avail - 0.5)), (float) $m->qty_purchase, 0.0001);   // Purchase = max(0, Required - Release)
            $this->assertEquals($req, (float) $m->qty_standar);                                // the engine number is kept
        } else {
            $this->edit(['required' => $req, 'release' => 0])->assertOk();                      // 0 = the default (no stock): no reason needed
            $this->assertEqualsWithDelta($req, (float) $this->stored()->qty_purchase ?: $req, 0.0001);
        }
    }

    public function test_release_and_purchase_that_do_not_agree_are_refused(): void
    {
        $req = (float) $this->line->qty_standar;

        $this->edit(['required' => $req, 'release' => 0, 'purchase' => $req + 5], 'x')->assertStatus(422);   // Purchase must be Required - Release
    }

    public function test_running_work_order_refuses_the_edit(): void
    {
        $this->prd->table('prd_wo')->where('wo_id', $this->line->wo_id)->update(['wo_status' => 'IN_PROGRESS']);

        $this->edit(['required' => 1], 'x')->assertStatus(422);
    }
}
