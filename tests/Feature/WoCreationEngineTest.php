<?php

namespace Tests\Feature;

use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdMrpRequirement;
use App\Models\Prd\PrdMrpRun;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use App\Services\Production\WoCreationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Bukti end-to-end: Engine MRP (App\Services\Production\MrpEngine) dan Engine
// MPS (App\Services\Scheduling\MpsEngine) benar-benar jalan "di balik layar
// saat pembuatan WO" lewat titik masuk tunggal WoCreationService -- belum ada
// layar "Buat WO" sungguhan (di luar scope), jadi ini test langsung ke service.
class WoCreationEngineTest extends TestCase
{
    protected ?PrdWo $wo = null;

    protected function tearDown(): void
    {
        if ($this->wo) {
            $run = PrdMrpRun::where('wo_id', $this->wo->wo_id)->first();
            if ($run) {
                if ($run->pr_id) {
                    PrLine::where('pr_id', $run->pr_id)->delete();
                    PrRequisition::whereKey($run->pr_id)->delete();
                }
                PrdMrpRequirement::where('mrp_run_id', $run->mrp_run_id)->delete();
                $run->delete();
            }

            DB::connection('tm2prd')->table('prd_wo_operation')->where('wo_id', $this->wo->wo_id)->delete();
            DB::connection('tm2prd')->table('prd_wo_material')->where('wo_id', $this->wo->wo_id)->delete();
            DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $this->wo->wo_id)->delete();
        }

        parent::tearDown();
    }

    public function test_wo_creation_triggers_mrp_and_mps_engines(): void
    {
        $machineId = ProdMachine::where('machine_code', 'CETAK-01')->value('machine_id');
        $this->assertNotNull($machineId, 'Fixture mesin CETAK-01 tidak ditemukan.');

        $category = MatCategory::level(1)->whereNotNull('default_coa_account_id')->firstOrFail();
        $material = MatRaw::where('category1', $category->category_name)->firstOrFail();

        $productId = DB::connection('tm2mst')->table('prod_product')->value('id_product');
        $this->assertNotNull($productId, 'Fixture produk tidak ditemukan.');

        $dueDate = Carbon::parse('next monday')->setTime(17, 0);

        $this->wo = app(WoCreationService::class)->create(
            header: [
                'wo_type' => 'MTO',
                'source_type' => 'CO_CONFIRMED',
                'product_id' => $productId,
                'qty' => 500,
                'due_date' => $dueDate,
            ],
            materials: [
                [
                    'material_id' => $material->material_id,
                    // Sengaja jauh di atas stok manapun -- pasti shortage, tak
                    // bergantung pada state stok riil yang bisa berubah-ubah.
                    'qty_required' => 999999999,
                    'qty_standar' => 999999999,
                ],
            ],
            operations: [
                [
                    'process_name' => 'Cetak',
                    'resource_type' => 'MACHINE',
                    'machine_id' => $machineId,
                    'qty' => 500,
                    'task_status' => 'PENDING',
                ],
            ],
            actorLogin: 'test_wo_creation_engine',
        );

        // ---- Engine MPS: target_start/target_finish (start/stop process) terisi,
        // WO pindah DRAFT -> SIMULATED (engine suggests, officer confirms).
        $op = PrdWoOperation::where('wo_id', $this->wo->wo_id)->firstOrFail();
        $this->assertNotNull($op->target_start, 'Engine MPS harus mengisi target_start (kapan proses mulai).');
        $this->assertNotNull($op->target_finish, 'Engine MPS harus mengisi target_finish (kapan proses stop).');
        $this->assertTrue($op->target_start->lt($op->target_finish));
        $this->assertSame('SIMULATED', $this->wo->fresh()->wo_status);

        // ---- Engine MRP: run + requirement tercatat, shortage terdeteksi,
        // PR draft otomatis terbit ke Procurement (i_source=MRP).
        $run = PrdMrpRun::where('wo_id', $this->wo->wo_id)->firstOrFail();
        $this->assertSame('PR_GENERATED', $run->status);
        $this->assertNotNull($run->pr_id);

        $requirement = PrdMrpRequirement::where('mrp_run_id', $run->mrp_run_id)->firstOrFail();
        $this->assertSame((float) 999999999, (float) $requirement->qty_required);
        $this->assertGreaterThan(0, (float) $requirement->qty_shortage);

        $pr = PrRequisition::findOrFail($run->pr_id);
        $this->assertSame('MRP', $pr->i_source);
        $this->assertSame('DRAFT', $pr->status);
        $this->assertNull($pr->doc_no);

        $line = PrLine::where('pr_id', $pr->pr_id)->firstOrFail();
        $this->assertSame($material->material_id, $line->item_id);
        $this->assertEquals((float) $requirement->qty_shortage, (float) $line->i_qty);
    }
}
