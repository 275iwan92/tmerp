<?php

/*
 * REMARK
 * Tujuan       : Menguji tombol Buy di Material Planning: satu bahan kurang menjadi PR Draft + PO Draft (supplier usulan, harga beli terakhir) dan PO tidak bisa diajukan sebelum PR Approved.
 * Sumber hulu  : MaterialPlanning::buy, PurchaseOrder::submit, PoBuilderService, PrAllocationService.
 * Input        : Satu bahan berstatus kurang dan punya supplier usulan dari data yang ada; semua dokumen buatan dihapus di tearDown.
 * Output       : Lulus / dilewati (tidak ada bahan kurang bersupplier).
 * Dipakai oleh : php artisan test --filter=MrpBuyTest --env=mockup
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Master\MatRaw;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrLineAlloc;
use App\Models\Pur\PrRequisition;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\MaterialPlanning;
use App\Support\V2\Data\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MrpBuyTest extends TestCase
{
    private ?PrRequisition $pr = null;

    private ?PoOrder $po = null;

    private bool $poWasNew = false;

    private ?array $restore = null;

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
        if ($this->restore) {
            MatRaw::query()->whereKey($this->restore['id'])->update(['suggest_supplier_id' => $this->restore['supplier'], 'last_unitprice' => $this->restore['price']]);
        }
        MaterialPlanning::changed();
        parent::tearDown();
    }

    public function test_buy_makes_a_draft_pr_and_po_and_the_po_waits_for_the_pr(): void
    {
        $this->actingAs(SecUser::query()->where('activate', 1)->firstOrFail());
        $short = collect(MaterialPlanning::engine()->plan()['materials'])->first(fn ($m) => ($m['material_id'] ?? 0) && $m['short'] > 0);
        $supplier = (int) DB::connection('tm2mst')->table('sup_supplier')->where('activate', 1)->value('id_supplier');
        ($short && $supplier) || $this->markTestSkipped('Tidak ada bahan kurang atau supplier di data ini.');
        // Master bahan dipinjam sebentar (supplier usulan + harga terakhir) dan dikembalikan di tearDown.
        $mat = MatRaw::query()->findOrFail($short['material_id']);
        $this->restore = ['id' => $mat->material_id, 'supplier' => $mat->suggest_supplier_id, 'price' => $mat->last_unitprice];
        $mat->update(['suggest_supplier_id' => $supplier, 'last_unitprice' => 1234]);
        MaterialPlanning::changed();
        $before = PoOrder::where('i_supplier_id', $supplier)->where('status', 'DRAFT')->exists();

        [$this->pr, $this->po] = MaterialPlanning::buy((int) $short['material_id']);
        $this->poWasNew = ! $before;

        $this->assertSame('DRAFT', $this->pr->status);
        $this->assertSame('DRAFT', $this->po->status);
        $this->assertSame($supplier, (int) $this->po->i_supplier_id);
        $this->assertEquals(1234, PrLine::where('pr_id', $this->pr->pr_id)->value('i_est_price'));
        $this->assertSame(1, PoLine::whereIn('alloc_id', PrLineAlloc::whereIn('pr_line_id', PrLine::where('pr_id', $this->pr->pr_id)->pluck('pr_line_id'))->pluck('alloc_id'))->count());
        $this->assertSame(t('A PR on this PO is not Approved yet.'), PurchaseOrder::submit($this->po->refresh()));
    }
}
