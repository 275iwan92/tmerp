<?php

/*
 * REMARK
 * Tujuan       : Menguji kolom Crew / Subcon di sheet BOP: pilihan (work center HRM + vendor subcon), simpan per operasi, satu pelaku saja, dan antrean Production Output.
 * Sumber hulu  : prd_wo_operation (crew_work_center_id, subcon_supplier_id), emp_work_center, sup_supplier, emp_employee_work_center_skill.
 * Input        : WO DRAFT di database dummy; transaksi di-rollback.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test (skip di tm2*).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Prd\PrdWoOperation;
use App\Support\V2\Data\Ppic;
use App\Support\V2\Data\ProductionOutput;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WoActorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! str_starts_with(DB::connection('tm2prd')->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Dummy database only.');
        }
        DB::connection('tm2prd')->beginTransaction();
        DB::connection('tm2mst')->beginTransaction();
        Ppic::forget();
    }

    protected function tearDown(): void
    {
        DB::connection('tm2mst')->rollBack();
        DB::connection('tm2prd')->rollBack();
        Ppic::forget();
        parent::tearDown();
    }

    public function test_actor_is_one_of_crew_or_subcon_and_shows_in_the_sheet(): void
    {
        $opts = Ppic::actorOptions();
        $crew = collect(array_keys($opts))->first(fn ($k) => str_starts_with((string) $k, 'C:'));
        $sub = collect(array_keys($opts))->first(fn ($k) => str_starts_with((string) $k, 'S:'));
        $this->assertNotNull($crew);
        $this->assertNotNull($sub);

        $op = PrdWoOperation::query()->orderBy('operation_id')->whereHas('wo', fn ($q) => $q->whereIn('wo_status', ['DRAFT', 'SIMULATED']))->first();
        $row = fn () => collect(Ppic::bopSheet($op->wo_id))->firstWhere('id', $op->operation_id)['actor'];

        $op->assignActor($crew);
        Ppic::forget();
        $this->assertSame($crew, $row());
        $this->assertNull($op->fresh()->subcon_supplier_id);

        $op->assignActor($sub);   // choosing a vendor clears the crew
        Ppic::forget();
        $this->assertSame($sub, $row());
        $this->assertNull($op->fresh()->crew_work_center_id);

        $op->assignActor('');
        Ppic::forget();
        $this->assertSame('', $row());
    }

    public function test_members_of_the_chosen_crew_get_the_job_in_production_output(): void
    {
        $op = collect(Ppic::operations())->first(fn ($o) => $o['plan_status'] === 'CONFIRMED' && $o['machine_id'] && $o['task_status'] === 'ASSIGNED');
        if (! $op) {
            $this->markTestSkipped('No confirmed operation.');
        }
        $employee = 987654322;
        $centerId = (int) DB::connection('tm2mst')->table('emp_work_center')->where('work_center_code', '!=', 'WC-LEGACY-UNMAPPED')->value('work_center_id');
        DB::connection('tm2mst')->table('emp_employee_work_center_skill')->insert(['employee_id' => $employee, 'work_center_id' => $centerId, 'activate' => 1]);
        PrdWoOperation::findOrFail($op['op_id'])->assignActor('C:'.$centerId);
        Ppic::forget();

        $desk = ProductionOutput::desk((object) ['employee_id' => $employee, 'is_admin' => false]);
        $this->assertArrayNotHasKey('empty', $desk);
        $this->assertSame('PRODUCTION PROCESS : '.$op['process'], $desk['title']);
    }
}
