<?php

/*
 * REMARK
 * Tujuan       : Menguji kolom Waste dan Spill di Bill of Material WO: nilai ProDev, timpaan officer, Required ikut selisih, PROCESS ulang tidak membuang timpaan, RESET mengembalikan, sidik PROCESS berubah.
 * Sumber hulu  : prd_wo_material (waste_engine_qty, spill_engine_qty, waste_override_qty, spill_override_qty), WoGenerationService::regenerateBom, SheetReset, Ppic::fingerprint.
 * Input        : WO DRAFT di database dummy; semuanya di transaksi yang di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test (DB_* ke ddt*; skip di tm2*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Prd\PrdWo;
use App\Models\Sys\SecUser;
use App\Services\Scheduling\Plan\SheetReset;
use App\Services\Scheduling\WoGenerationService;
use App\Support\V2\Data\Ppic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WoBomWasteSpillTest extends TestCase
{
    private $prd;

    private int $wo;

    private int $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prd = DB::connection('tm2prd');
        if (! str_starts_with($this->prd->getDatabaseName(), 'ddt') || ! Schema::connection('tm2prd')->hasColumn('prd_wo_material', 'waste_override_qty')) {
            $this->markTestSkipped('Dummy database with the waste/spill migration only.');
        }
        $row = $this->prd->table('prd_wo_material as m')->join('prd_wo as w', 'w.wo_id', '=', 'm.wo_id')
            ->where('w.wo_status', 'DRAFT')->where('m.qty_standar', '>', 0)->whereNotNull('m.material_id')->orderBy('m.detail_id')->first(['m.detail_id', 'm.wo_id']);
        if (! $row) {
            $this->markTestSkipped('No Draft Work Order with a material line.');
        }
        $this->wo = (int) $row->wo_id;
        $this->line = (int) $row->detail_id;
        $this->actingAs(SecUser::where('is_admin', 1)->firstOrFail());
        $this->prd->beginTransaction();
        // ProDev value of the test line (set as the engine would have written it)
        $this->prd->table('prd_wo_material')->where('detail_id', $this->line)->update(['waste_engine_qty' => 10, 'spill_engine_qty' => 4, 'waste_override_qty' => null, 'spill_override_qty' => null]);
        Ppic::forget();
    }

    protected function tearDown(): void
    {
        $this->prd?->rollBack();
        Ppic::forget();
        parent::tearDown();
    }

    private function row(): array
    {
        Ppic::forget();

        return collect(Ppic::bomSheet($this->wo))->firstWhere('id', $this->line);
    }

    private function edit(array $cells)
    {
        return $this->postJson(route('v2.ppic.wo.bom-cells', $this->wo), ['changed' => [['id' => $this->line] + $cells], 'reason' => 'Test']);
    }

    public function test_sheet_shows_prodev_values_and_an_override_shifts_required_and_is_marked(): void
    {
        $before = $this->row();
        $this->assertEquals(10.0, $before['waste']);
        $this->assertEquals(4.0, $before['spill']);

        $this->edit(['waste' => '15'])->assertOk();
        $after = $this->row();
        $this->assertEquals(15.0, $after['waste']);
        $this->assertEquals(4.0, $after['spill']);
        $this->assertEquals(round($before['required'] + 5, 2), $after['required']);
        $this->assertStringContainsString('waste/spill edited', $after['material']);

        // typing the ProDev value again is no override
        $this->edit(['waste' => '10'])->assertOk();
        $back = $this->row();
        $this->assertEquals($before['required'], $back['required']);
        $this->assertNull($this->prd->table('prd_wo_material')->where('detail_id', $this->line)->value('waste_override_qty'));
    }

    public function test_override_survives_process_and_reset_gives_prodev_back(): void
    {
        $this->edit(['spill' => '9'])->assertOk();
        $wo = PrdWo::findOrFail($this->wo);
        app(WoGenerationService::class)->regenerateBom($wo);
        $kept = $this->prd->table('prd_wo_material')->where('wo_id', $this->wo)->where('spill_override_qty', 9)->count();
        $this->assertSame(1, $kept, 'the officer override must survive the BOM rebuild');

        app(SheetReset::class)->run($wo->fresh(), 'MAT', 'test');
        $this->assertSame(0, $this->prd->table('prd_wo_material')->where('wo_id', $this->wo)->whereNotNull('spill_override_qty')->count());
    }

    public function test_override_changes_the_process_fingerprint(): void
    {
        $a = Ppic::fingerprint($this->wo);
        $this->prd->table('prd_wo_material')->where('detail_id', $this->line)->update(['waste_override_qty' => 77]);
        $this->assertNotSame($a, Ppic::fingerprint($this->wo));
    }
}
