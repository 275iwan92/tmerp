<?php

/*
 * REMARK
 * Tujuan       : Menguji bahwa harga baris PO yang dibuat dari alokasi PR kertas diisi PaperPriceEngine (supplier PO + tanggal PO), dan tanpa harga kertas tetap memakai harga taksiran PR.
 * Sumber hulu  : PoBuilderService::addAllocation, PaperPriceEngine, PrAllocationService.
 * Input        : Satu bahan kertas dengan gramasi + ukuran (data dummy), satu supplier tanpa harga kertas; baris harga uji ditandai created_by.
 * Output       : Lulus / dilewati (tidak ada bahan kertas berukuran di data ini); semua dokumen buatan dihapus di tearDown.
 * Dipakai oleh : php artisan test --filter=PaperPricePrPoTest --env=mockup
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Master\MatRaw;
use App\Models\Master\SupPaperPrice;
use App\Models\Master\SupSupplier;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrLineAlloc;
use App\Models\Pur\PrRequisition;
use App\Models\Sys\SecUser;
use App\Services\Pur\PaperPriceEngine;
use App\Services\Pur\PoBuilderService;
use App\Services\Pur\PrAllocationService;
use App\Support\V2\Data\PurchaseRequisition;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaperPricePrPoTest extends TestCase
{
    private const BY = 'test_paper_price_po';

    private ?PrRequisition $pr = null;

    private ?PoOrder $po = null;

    private bool $poWasNew = false;

    protected function tearDown(): void
    {
        if ($this->pr) {
            $lines = PrLine::where('pr_id', $this->pr->pr_id)->pluck('pr_line_id');
            $allocs = PrLineAlloc::whereIn('pr_line_id', $lines)->pluck('alloc_id');
            PoLine::whereIn('alloc_id', $allocs)->delete();
            PrLineAlloc::whereIn('alloc_id', $allocs)->delete();
            PrLine::whereIn('pr_line_id', $lines)->delete();
            $this->pr->delete();
            if ($this->po && $this->poWasNew) {
                $this->po->delete();
            }
        }
        SupPaperPrice::query()->where('created_by', self::BY)->delete();
        $this->assertSame(0, SupPaperPrice::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    public function test_the_po_line_price_comes_from_the_engine_for_the_po_supplier(): void
    {
        $this->actingAs(SecUser::query()->where('activate', 1)->firstOrFail());
        $m = MatRaw::query()->where('material_name', 'like', '%ivory%')->where('gram_weight', '>', 0)->where('cm_length', '>', 0)->where('cm_width', '>', 0)
            ->whereIn('uom_purchase', ['RIM', 'LBR'])->first();
        $supplier = (int) SupSupplier::query()->where('activate', true)->whereNotIn('id_supplier', SupPaperPrice::query()->select('supplier_id'))->value('id_supplier');
        ($m && $supplier) || $this->markTestSkipped('Tidak ada bahan ivory berukuran atau supplier bebas di data ini.');
        SupPaperPrice::create(['supplier_id' => $supplier, 'paper_kind' => 'IVORY', 'sheet_price_per_kg' => 12500, 'price_basis' => 'INCL', 'effective_date' => '2026-01-01',
            'activate' => true, 'created_by' => self::BY]);
        $dept = (int) DB::connection('tm2mst')->table('org_department')->where('activate', 1)->value('department_id');
        $this->pr = PurchaseRequisition::create($dept);
        $line = PrLine::create(['pr_id' => $this->pr->pr_id, 'line_no' => 1, 'i_item_source' => 'MASTER_ITEM', 'item_id' => $m->material_id, 'c_item_code' => $m->material_code,
            'i_description' => $m->material_name, 'i_qty' => 2, 'i_uom' => $m->uom_purchase, 'i_est_price' => 777, 'i_department_id' => $dept,
            'i_coa_account_id' => DB::connection('tm2acc')->table('fin_account')->where('activate', 1)->value('account_id'), 'created_by' => self::BY]);
        $alloc = PrAllocationService::ensureDefaultAllocation($line);
        $this->poWasNew = ! PoOrder::where('i_supplier_id', $supplier)->where('status', 'DRAFT')->exists();
        $this->po = PoBuilderService::findOrCreateDraft($supplier);

        $poLine = PoBuilderService::addAllocation($this->po, $alloc);

        $expected = app(PaperPriceEngine::class)->priceFor($m, $supplier, Carbon::parse($this->po->i_doc_date));
        $this->assertNotNull($expected);
        $this->assertEqualsWithDelta($expected, (float) $poLine->i_price, 0.01);
        $this->assertNotEquals(777, (float) $poLine->i_price, 'The PR estimate is replaced by the supplier price.');
    }
}
