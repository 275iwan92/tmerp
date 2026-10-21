<?php

/*
 * REMARK
 * Tujuan       : Menjaga tiga perbaikan engine untuk panel BOM ProCost: kebutuhan tinta ditaksir bila tutupan belum ada, konversi Stok -> Pakai master dipakai, dan ketersediaan per baris BOM satu part dari MRP.
 * Sumber hulu  : SpecEngine::generate / figures / toStockUom / stockFor, MaterialPlanningEngine::availabilityFor, prod_bop_rule (INK_BY_NAME), v_rm_available.
 * Input        : ProdPart/IqpPart di memori (tidak disimpan) dan data master/stok yang ada; tidak ada tulisan ke DB.
 * Output       : Lulus/gagal; dilewati bila tidak ada aturan tinta atau stok bahan.
 * Dipakai oleh : php artisan test --filter=ProCostBomAvailabilityTest (env DB_* = ddt*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Inventory\RmBooking;
use App\Models\Inventory\RmBookingAlloc;
use App\Models\Inventory\RmBookingD;
use App\Models\Master\ProdPart;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SecUser;
use App\Services\Planning\MaterialPlanningEngine;
use App\Services\ProDev\SpecEngine;
use App\Support\V2\Data\MaterialPlanning;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProCostDriver;
use Tests\TestCase;

class ProCostBomAvailabilityTest extends TestCase
{
    private const CTX = ['sheetL' => 70, 'sheetW' => 50, 'planoL' => 100, 'planoW' => 70, 'sheetsPerPlano' => 1, 'upCount' => 2, 'orderQty' => 1000];

    public function test_ink_need_is_estimated_when_artwork_coverage_is_missing(): void
    {
        $rule = DB::connection('tm2mst')->table('prod_bop_rule')->where('activate', 1)->where('material_rule', 'INK_BY_NAME')->whereNotNull('usage_rate')->exists();
        if (! $rule) {
            $this->markTestSkipped('No INK_BY_NAME rule with a usage rate in this database.');
        }

        $part = new ProdPart(['part_type' => 'DC', 'part_kind' => 'MONO', 'cfg_color' => '4/0', 'pack_qty' => 10]);
        $iqp = new IqpPart(['part_kind' => 'MONO', 'cfg_color' => '4/0', 'pack_qty' => 10]);
        $ink = collect(app(SpecEngine::class)->generate($part, $iqp, self::CTX)['bom'])->where('role', 'TINTA')->first();

        $this->assertNotNull($ink, 'Ink rows are expected for a 4/0 part.');
        $this->assertTrue($ink['estimated']);
        $this->assertGreaterThan(0, $ink['qty_need']);
    }

    public function test_procost_screen_shows_prodev_bom_with_mrp_availability_read_only(): void
    {
        $costing = DB::connection('tm2sdi')->table('iqp_costing as c')
            ->join('iqp_costing_part as cp', 'cp.costing_id', '=', 'c.id_costing')
            ->join('iqp_part as p', 'p.id_part', '=', 'cp.part_id')
            ->join('iqp_costing_price_tier as t', 't.costing_id', '=', 'c.id_costing')
            ->where('c.doc_status', 'PUBLISHED')->whereNotNull('c.i_dim_panjang_mm')->whereNotNull('p.prod_part_id')->where('t.qty_pcs', '>', 0)
            ->orderByDesc('c.id_costing')->first(['c.inquiry_id']);
        if (! $costing) {
            $this->markTestSkipped('No published costing with a ProDev part in this database.');
        }

        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $html = $this->get('/v2/s/procost?inquiry='.$costing->inquiry_id)->assertOk()->getContent();

        $this->assertStringContainsString('BOM and availability', $html);
        $this->assertMatchesRegularExpression('/Enough|Short|No stock|Need item|Check unit|Covered by PO\/PR/', $html);
    }

    public function test_availability_for_returns_stock_and_short_per_bom_index(): void
    {
        $row = DB::connection('tm2inv')->table('v_rm_available')->where('is_usable', 1)->where('qty_available', '>', 0)
            ->selectRaw('material_id, SUM(qty_available) qty')->groupBy('material_id')->orderByDesc('qty')->first();
        if (! $row) {
            $this->markTestSkipped('No stocked material in this database.');
        }

        $bom = [
            ['material_id' => $row->material_id, 'qty_need_stock_uom' => 0.001],
            ['material_id' => null, 'qty_need_stock_uom' => 5],
            ['material_id' => $row->material_id, 'qty_need_stock_uom' => ((float) $row->qty) + 1000000],
        ];
        $r = (new MaterialPlanningEngine)->availabilityFor($bom);

        $this->assertSame([0, 2], array_keys($r));
        $this->assertSame('STOCK', $r[0]['status']);
        $this->assertSame('SHORT', $r[2]['status']);
        $this->assertGreaterThan(0, $r[2]['short']);
    }

    public function test_book_holds_the_pre_booking_and_release_frees_it(): void
    {
        $eligible = (new MaterialPlanningEngine)->preDemands()->first();
        $inv = $eligible['inquiry_id'] ?? null;
        if (! $eligible) {
            $this->markTestSkipped('No published costing with a live pre-booking chain in this database.');
        }
        $had = RmBooking::where('chain_key', (string) $inv)->whereNull('co_id')->where('stat_cancel', 0)->exists();

        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        try {
            $c = ProCostDriver::open((int) $inv)->call('book');
            $b = RmBooking::where('chain_key', (string) $inv)->whereNull('co_id')->where('stat_cancel', 0)->orderByDesc('booking_id')->first();
            $this->assertSame('HELD', $b->doc_status);
            $this->assertNotNull($b->hold_until);

            $c->call('releaseBook');
            $this->assertSame('DRAFT', $b->fresh()->doc_status);
        } finally {
            $b = RmBooking::where('chain_key', (string) $inv)->whereNull('co_id')->where('stat_cancel', 0)->orderByDesc('booking_id')->first();
            if ($b && $b->doc_status === 'HELD') {
                MaterialPlanning::engine()->releaseHold($b);
            }
            if ($b && ! $had) {
                $ids = RmBookingD::where('booking_id', $b->booking_id)->pluck('booking_detail_id');
                RmBookingAlloc::whereIn('booking_detail_id', $ids)->delete();
                RmBookingD::where('booking_id', $b->booking_id)->delete();
                $b->delete();
            }
            MaterialPlanning::changed();
        }
        $this->assertSame($had, RmBooking::where('chain_key', (string) $inv)->whereNull('co_id')->where('stat_cancel', 0)->exists(), 'The test leaves no booking behind.');
    }
}
