<?php

/*
 * REMARK
 * Tujuan       : Menjaga master Process v2: simpan (kode huruf besar, angka kosong tetap NULL bukan 0, bendera, kolom di luar form tidak tersentuh), aturan ditolak (kode / nama unik, rentang, lot, tarif tanpa basis), matriks mesin (pasangan baru, ubah, nonaktif tanpa hapus, hidupkan lagi, hanya mesin proses aktif, tidak ganda, gramatur), nilai efektif, aktif / nonaktif, layar, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Production\ProcessMasterService dan ProcessMachineMatrixService, layar prod-process-form, routes/v2_production.php, ProcessGrid.
 * Input        : User johiwan (admin); satu mesin proses real yang sudah ada (hanya dibaca). Proses uji dibuat di dalam transaksi tm2mst yang di-rollback, jadi 29 proses dan 68 pasangan real tidak disentuh dan tidak ada data tersisa.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=ProcessMasterServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProcessMachine;
use App\Models\Sys\SecUser;
use App\Services\Production\ProcessMachineMatrixService;
use App\Services\Production\ProcessMasterService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcessMasterServiceTest extends TestCase
{
    private int $machine;

    private int $machine2;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2mst')->beginTransaction();
        $ids = ProdMachine::where('activate', true)->where('is_process_machine', true)->orderBy('machine_id')->limit(2)->pluck('machine_id');
        $this->machine = (int) $ids[0];
        $this->machine2 = (int) $ids[1];
    }

    protected function tearDown(): void
    {
        while (DB::connection('tm2mst')->transactionLevel() > 0) {
            DB::connection('tm2mst')->rollBack();
        }
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function in(array $over = []): array
    {
        return array_merge(['process_code' => 'zz9', 'process_name' => 'ZZ Test Process', 'process_stage' => 'FINISHING', 'inhouse' => 'true', 'default_speed_per_hour' => '1.500,5', 'default_waste_pct' => '', 'default_setup_minutes' => '', 'cost_basis' => '', 'standard_rate' => ''], $over);
    }

    private function row(int $machine, array $over = []): array
    {
        return array_merge(['capability_id' => '', 'other_id' => $machine, 'primary' => 'false', 'is_certified' => 'true', 'activate' => 'true', 'setup_minutes' => '', 'speed_per_hour' => '', 'waste_pct' => '', 'waste_fixed_qty' => '', 'hourly_rate' => '', 'manpower' => '', 'gsm_min' => '', 'gsm_max' => '', 'note' => ''], $over);
    }

    public function test_save_keeps_empty_numbers_null_and_leaves_other_columns(): void
    {
        $svc = app(ProcessMasterService::class);
        $before = ProdProcess::count();
        $p = $svc->save(null, $this->in(), [], $this->admin());
        $this->assertSame($before + 1, ProdProcess::count());
        $p = $p->fresh();
        $this->assertSame('ZZ9', $p->process_code);
        $this->assertSame(1500.5, (float) $p->default_speed_per_hour);
        $this->assertNull($p->default_waste_pct, 'kosong tetap NULL');
        $this->assertNull($p->default_setup_minutes);
        $this->assertNull($p->standard_rate);
        $this->assertTrue((bool) $p->inhouse);
        $this->assertFalse((bool) $p->is_bottleneck);
        $this->assertTrue((bool) $p->activate);

        // Kolom di luar form tidak tersentuh.
        $p->update(['washup_minutes' => 7, 'qc_blocking' => 1]);
        $p = $svc->save($p, $this->in(['process_name' => 'ZZ Renamed', 'default_waste_pct' => '2,5', 'cost_basis' => 'PER_HOUR', 'standard_rate' => '100000', 'is_bottleneck' => '1', 'inhouse' => 'false']), [], $this->admin())->fresh();
        $this->assertSame('ZZ Renamed', $p->process_name);
        $this->assertSame(2.5, (float) $p->default_waste_pct);
        $this->assertTrue((bool) $p->is_bottleneck);
        $this->assertFalse((bool) $p->inhouse, 'teks false dibaca salah');
        $this->assertSame(7, (int) $p->washup_minutes);
        $this->assertSame(1, (int) $p->qc_blocking);

        // Warna Gantt kosong tidak diisi warna bawaan pemilih; warna yang dipilih tersimpan.
        $this->assertNull($svc->save($p, $this->in(['process_name' => 'ZZ Renamed', 'gantt_color' => '#2563EB']), [], $this->admin())->fresh()->gantt_color);
        $this->assertSame('#112233', $svc->save($p, $this->in(['process_name' => 'ZZ Renamed', 'gantt_color' => '#112233']), [], $this->admin())->fresh()->gantt_color);

        $svc->setActive($p, false, $this->admin());
        $this->assertFalse((bool) $p->fresh()->activate);
        $svc->setActive($p, true, $this->admin());
        $this->assertTrue((bool) $p->fresh()->activate);
        $this->assertSame('Created', $svc->history($p->fresh())[count($svc->history($p->fresh())) - 1]['action']);
    }

    public function test_rules_are_refused(): void
    {
        $svc = app(ProcessMasterService::class);
        $first = $svc->save(null, $this->in(), [], $this->admin());
        $before = ProdProcess::count();
        $cases = [
            'name blank' => $this->in(['process_name' => '']),
            'duplicate name' => $this->in(['process_code' => 'zz8', 'process_name' => 'ZZ Test Process']),
            'duplicate code' => $this->in(['process_name' => 'ZZ Other', 'process_code' => 'ZZ9']),
            'code too long' => $this->in(['process_code' => 'ABCDEFGHIJK']),
            'code bad chars' => $this->in(['process_code' => 'A B']),
            'bad stage' => $this->in(['process_stage' => 'XX']),
            'bad uom' => $this->in(['output_uom' => 'XX']),
            'bad cost basis' => $this->in(['cost_basis' => 'XX']),
            'waste over 100' => $this->in(['default_waste_pct' => '101']),
            'negative speed' => $this->in(['default_speed_per_hour' => '-1']),
            'setup too big' => $this->in(['default_setup_minutes' => '70000']),
            'crew too big' => $this->in(['default_manpower' => '256']),
            'max lot below min' => $this->in(['min_lot_qty' => '100', 'max_lot_qty' => '50']),
            'rate without basis' => $this->in(['standard_rate' => '5000']),
        ];
        foreach ($cases as $name => $in) {
            try {
                $svc->save(null, $in, [], $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame($before, ProdProcess::count());
        $this->assertSame('ZZ Test Process', $first->fresh()->process_name);
    }

    public function test_machine_matrix_saves_deactivates_and_reactivates_without_delete(): void
    {
        $svc = app(ProcessMasterService::class);
        $p = $svc->save(null, $this->in(), [$this->row($this->machine, ['primary' => 'true', 'speed_per_hour' => '2000', 'note' => ' ZZ '])], $this->admin());
        $cap = ProdProcessMachine::where('process_id', $p->id_process)->where('machine_id', $this->machine)->firstOrFail();
        $this->assertSame(1, (int) $cap->preference_rank);
        $this->assertTrue((bool) $cap->is_certified);
        $this->assertSame(2000.0, (float) $cap->speed_per_hour);
        $this->assertNull($cap->setup_minutes, 'kosong tetap NULL, bukan 0');
        $this->assertNull($cap->waste_pct);
        $this->assertSame('ZZ', $cap->note);

        // Ubah + pasangan kedua; yang pertama dinonaktifkan.
        $svc->save($p, $this->in(), [$this->row($this->machine, ['capability_id' => $cap->capability_id, 'activate' => 'false', 'speed_per_hour' => '2000']), $this->row($this->machine2, ['setup_minutes' => '30', 'gsm_min' => '100', 'gsm_max' => '300'])], $this->admin());
        $this->assertSame(2, ProdProcessMachine::where('process_id', $p->id_process)->count(), 'tidak ada yang dihapus');
        $this->assertFalse((bool) $cap->fresh()->activate);
        $this->assertSame(1, $svc->machineCount($p));

        // Pasangan lama dipilih lagi sebagai baris baru: dihidupkan kembali, bukan ganda.
        $svc->save($p, $this->in(), [$this->row($this->machine, ['activate' => 'true', 'speed_per_hour' => '2000'])], $this->admin());
        $this->assertSame(2, ProdProcessMachine::where('process_id', $p->id_process)->count());
        $this->assertTrue((bool) $cap->fresh()->activate);
        $this->assertSame(2, $svc->machineCount($p));

        $eff = app(ProcessMachineMatrixService::class)->effective('process', (int) $p->id_process);
        $this->assertCount(2, $eff);
        $this->assertSame(2000.0, (float) collect($eff)->firstWhere('speed_source', 'CAPABILITY')['speed']);
    }

    public function test_matrix_rules_are_refused_and_nothing_is_saved(): void
    {
        $svc = app(ProcessMasterService::class);
        $inactive = ProdMachine::where('activate', false)->value('machine_id');
        $cases = [
            'unknown machine' => [$this->row(999999999)],
            'duplicate machine' => [$this->row($this->machine), $this->row($this->machine)],
            'setup too big' => [$this->row($this->machine, ['setup_minutes' => '70000'])],
            'negative speed' => [$this->row($this->machine, ['speed_per_hour' => '-1'])],
            'waste over 100' => [$this->row($this->machine, ['waste_pct' => '101'])],
            'crew too big' => [$this->row($this->machine, ['manpower' => '256'])],
            'gsm max below min' => [$this->row($this->machine, ['gsm_min' => '300', 'gsm_max' => '100'])],
            'note too long' => [$this->row($this->machine, ['note' => str_repeat('n', 256)])],
            'foreign pair' => [$this->row($this->machine, ['capability_id' => (int) ProdProcessMachine::where('process_id', '!=', 0)->value('capability_id')])],
        ];
        if ($inactive) {
            $cases['inactive machine'] = [$this->row((int) $inactive)];
        }
        $pairs = ProdProcessMachine::count();
        $procs = ProdProcess::count();
        foreach ($cases as $name => $rows) {
            try {
                $svc->save(null, $this->in(['process_name' => 'ZZ M '.$name, 'process_code' => '']), $rows, $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('machines', $e->errors(), $name);
            }
        }
        $this->assertSame($pairs, ProdProcessMachine::count());
        $this->assertSame($procs, ProdProcess::count(), 'proses ikut dibatalkan bila matriks salah');
    }

    public function test_screens_routes_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'prod-process-form');
        $this->get(route('v2.screen', 'prod-process'))->assertOk();
        $this->get($form.'?new=1')->assertOk()->assertSee('name="process_name"', false)->assertSee('Executing machines');
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();

        $this->post(route('v2.prod.process.save'), $this->in(['machines' => [$this->row($this->machine)]]))->assertRedirect();
        $p = ProdProcess::where('process_name', 'ZZ Test Process')->firstOrFail();
        $open = $form.'?id='.$p->id_process;
        $this->get($open)->assertOk()->assertSee('ZZ Test Process')->assertSee('name="default_speed_per_hour"', false);
        $this->post(route('v2.prod.process.save'), $this->in(['id' => $p->id_process, 'process_name' => '']))->assertSessionHasErrors('process_name');
        $this->post(route('v2.prod.process.save'), $this->in(['id' => $p->id_process, 'machines' => [$this->row(999999999)]]))->assertSessionHasErrors('machines');
        $this->post(route('v2.prod.process.save'), ['id' => $p->id_process, 'action' => 'deactivate'])->assertRedirect($open);
        $this->assertFalse((bool) $p->fresh()->activate);
        $this->post(route('v2.prod.process.save'), ['id' => $p->id_process, 'action' => 'activate'])->assertRedirect($open);
        $this->assertTrue((bool) $p->fresh()->activate);

        $grid = Grids::find('prod-process');
        $this->assertStringContainsString('/v2/s/prod-process-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/prod-process-form?new=1', $grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/production/master-process.blade.php'));
        $this->assertArchived('production_master_process_v1_2026-10-19.blade.php');
        $this->assertArrayNotHasKey('PROD_MST_PROCESS', MenuRoutes::map());
        $this->assertArrayNotHasKey('PRE_MD_PROCESS', MenuRoutes::map());
        $this->get('/production/master-process')->assertRedirect('/v2/s/prod-process');
        $this->get('/production/master-process?new=1')->assertRedirect('/v2/s/prod-process-form?new=1');
        $this->get('/production/master-process?open=4')->assertRedirect('/v2/s/prod-process-form?id=4');
    }
}
