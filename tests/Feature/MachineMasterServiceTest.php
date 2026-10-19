<?php

/*
 * REMARK
 * Tujuan       : Menjaga master Machine v2: simpan (kode huruf besar, angka kosong tetap NULL bukan 0, bendera, kolom di luar form tidak tersentuh), aturan ditolak (kode unik, jenis, ukuran lembar, gsm, area cetak, target kecepatan, shift, dst.), matriks proses sisi mesin (baru, nonaktif tanpa hapus, hidupkan lagi, proses manual ditolak), log downtime (stop baru, menutup dengan End, baris yang ada terkunci, terencana ditentukan jenis, hitung ulang rencana ditunda sampai commit), Deactivate / Activate, OEE dan di mana dipakai, layar, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Production\MachineMasterService dan ProcessMachineMatrixService, layar prod-machine-form, routes/v2_production.php, MachineGrid.
 * Input        : User johiwan (admin); satu proses non-manual real (hanya dibaca). Mesin uji dibuat di dalam transaksi tm2mst yang di-rollback, jadi 45 mesin real tidak disentuh dan tidak ada data tersisa; hitung ulang rencana PPIC (tm2prd) tidak berjalan karena menunggu commit.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=MachineMasterServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProcessMachine;
use App\Models\Prd\PrdPlanRun;
use App\Models\Sys\SecUser;
use App\Services\Production\MachineMasterService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MachineMasterServiceTest extends TestCase
{
    private int $process;

    private int $manual;

    private int $planRuns;

    private int $division;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2mst')->beginTransaction();
        $this->process = (int) ProdProcess::where('activate', true)->where('is_manual', false)->orderBy('id_process')->value('id_process');
        $this->manual = (int) ProdProcess::where('activate', true)->where('is_manual', true)->orderBy('id_process')->value('id_process');
        $this->planRuns = PrdPlanRun::count();
        $this->division = (int) OrgDivision::where('activate', true)->orderBy('division_id')->value('division_id');
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
        return array_merge(['division_id' => $this->division, 'machine_code' => 'zz-t1', 'machine_name' => 'ZZ Test Machine', 'machine_type' => 'Die Cutting', 'machine_status' => 'READY', 'is_process_machine' => 'true', 'max_speed_per_hour' => '4500', 'hourly_rate' => '', 'oee_target_pct' => ''], $over);
    }

    private function proc(int $process, array $over = []): array
    {
        return array_merge(['capability_id' => '', 'other_id' => $process, 'primary' => 'false', 'is_certified' => 'true', 'activate' => 'true', 'setup_minutes' => '', 'speed_per_hour' => '', 'waste_pct' => '', 'waste_fixed_qty' => '', 'hourly_rate' => '', 'manpower' => '', 'gsm_min' => '', 'gsm_max' => '', 'note' => ''], $over);
    }

    private function stop(array $over = []): array
    {
        return array_merge(['downtime_id' => '', 'kind' => 'BREAKDOWN', 'start_date' => '2026-10-01', 'start_time' => '07:30', 'end_date' => '', 'end_time' => '', 'cause' => ' ZZ jam '], $over);
    }

    public function test_save_keeps_empty_numbers_null_and_leaves_other_columns(): void
    {
        $svc = app(MachineMasterService::class);
        $before = ProdMachine::count();
        $m = $svc->save(null, $this->in(), [], [], $this->admin())->fresh();
        $this->assertSame($before + 1, ProdMachine::count());
        $this->assertSame('ZZ-T1', $m->machine_code);
        $this->assertSame(4500, (int) $m->max_speed_per_hour);
        foreach (['hourly_rate', 'oee_target_pct', 'shifts_per_day', 'hours_per_shift', 'gsm_min', 'speed_target_per_hour', 'acquired_date', 'brand_model'] as $col) {
            $this->assertNull($m->{$col}, $col.' kosong tetap NULL');
        }
        $this->assertTrue((bool) $m->is_process_machine);
        $this->assertFalse((bool) $m->perfecting);
        $this->assertTrue((bool) $m->activate);

        $m->update(['legacy_machine_names' => 'OLD NAME', 'photo_path' => 'x.jpg']);
        $m = $svc->save($m, $this->in(['machine_name' => 'ZZ Renamed', 'perfecting' => '1', 'is_process_machine' => 'false', 'hourly_rate' => '250000.5', 'oee_target_pct' => '82.5', 'next_maintenance_date' => '2026-11-01']), [], [], $this->admin())->fresh();
        $this->assertSame('ZZ Renamed', $m->machine_name);
        $this->assertTrue((bool) $m->perfecting);
        $this->assertFalse((bool) $m->is_process_machine, 'teks false dibaca salah');
        $this->assertSame(250000.5, (float) $m->hourly_rate);
        $this->assertSame(82.5, (float) $m->oee_target_pct);
        $this->assertSame('2026-11-01', $m->next_maintenance_date->toDateString());
        $this->assertSame('OLD NAME', $m->legacy_machine_names);
        $this->assertSame('x.jpg', $m->photo_path);
        $this->assertSame('Created', $svc->history($m)[count($svc->history($m)) - 1]['action']);

        $svc->setActive($m, false, $this->admin());
        $this->assertFalse((bool) $m->fresh()->activate);
        $this->assertSame('RETIRED', $m->fresh()->machine_status);
        $svc->setActive($m, true, $this->admin());
        $this->assertTrue((bool) $m->fresh()->activate);
        $this->assertSame('READY', $m->fresh()->machine_status);
    }

    public function test_rules_are_refused(): void
    {
        $svc = app(MachineMasterService::class);
        $first = $svc->save(null, $this->in(), [], [], $this->admin());
        $before = ProdMachine::count();
        $cases = [
            'code blank' => $this->in(['machine_code' => '']),
            'code too long' => $this->in(['machine_code' => str_repeat('A', 21)]),
            'duplicate code' => $this->in(['machine_code' => 'zz-t1']),
            'no division' => $this->in(['machine_code' => 'ZZ-T2', 'division_id' => '']),
            'name blank' => $this->in(['machine_code' => 'ZZ-T2', 'machine_name' => '']),
            'bad type' => $this->in(['machine_code' => 'ZZ-T2', 'machine_type' => 'Subkontraktor']),
            'bad status' => $this->in(['machine_code' => 'ZZ-T2', 'machine_status' => 'XX']),
            'bad criticality' => $this->in(['machine_code' => 'ZZ-T2', 'criticality' => 'Z']),
            'bad drying' => $this->in(['machine_code' => 'ZZ-T2', 'drying_system' => 'XX']),
            'unknown model' => $this->in(['machine_code' => 'ZZ-T2', 'machine_model_id' => 999999999]),
            'year too early' => $this->in(['machine_code' => 'ZZ-T2', 'year_acquired' => '1900']),
            'bad date' => $this->in(['machine_code' => 'ZZ-T2', 'next_maintenance_date' => 'abc']),
            'max sheet length below min' => $this->in(['machine_code' => 'ZZ-T2', 'max_sheet_length_mm' => '300', 'min_sheet_length_mm' => '400']),
            'max sheet width below min' => $this->in(['machine_code' => 'ZZ-T2', 'max_sheet_width_mm' => '300', 'min_sheet_width_mm' => '400']),
            'gsm max below min' => $this->in(['machine_code' => 'ZZ-T2', 'gsm_max' => '100', 'gsm_min' => '300']),
            'print longer than sheet' => $this->in(['machine_code' => 'ZZ-T2', 'max_print_length_mm' => '900', 'max_sheet_length_mm' => '800']),
            'target above max speed' => $this->in(['machine_code' => 'ZZ-T2', 'speed_target_per_hour' => '9000', 'max_speed_per_hour' => '4500']),
            'shifts over 4' => $this->in(['machine_code' => 'ZZ-T2', 'shifts_per_day' => '5']),
            'hours over 24' => $this->in(['machine_code' => 'ZZ-T2', 'hours_per_shift' => '25']),
            'oee over 100' => $this->in(['machine_code' => 'ZZ-T2', 'oee_target_pct' => '101']),
            'negative rate' => $this->in(['machine_code' => 'ZZ-T2', 'hourly_rate' => '-1']),
        ];
        foreach ($cases as $name => $in) {
            try {
                $svc->save(null, $in, [], [], $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame($before, ProdMachine::count());
        $this->assertSame('ZZ Test Machine', $first->fresh()->machine_name);
    }

    public function test_process_matrix_from_the_machine_side(): void
    {
        $svc = app(MachineMasterService::class);
        $m = $svc->save(null, $this->in(), [$this->proc($this->process, ['primary' => 'true', 'speed_per_hour' => '3000'])], [], $this->admin());
        $cap = ProdProcessMachine::where('machine_id', $m->machine_id)->where('process_id', $this->process)->firstOrFail();
        $this->assertSame(1, (int) $cap->preference_rank);
        $this->assertSame(3000.0, (float) $cap->speed_per_hour);
        $this->assertNull($cap->setup_minutes, 'kosong tetap NULL, bukan 0');
        $this->assertSame(1, $svc->processCount($m));

        $svc->save($m, $this->in(), [$this->proc($this->process, ['capability_id' => $cap->capability_id, 'activate' => 'false', 'speed_per_hour' => '3000'])], [], $this->admin());
        $this->assertSame(1, ProdProcessMachine::where('machine_id', $m->machine_id)->count(), 'tidak ada yang dihapus');
        $this->assertFalse((bool) $cap->fresh()->activate);
        $this->assertSame(0, $svc->processCount($m));

        $svc->save($m, $this->in(), [$this->proc($this->process, ['speed_per_hour' => '3000'])], [], $this->admin());
        $this->assertSame(1, ProdProcessMachine::where('machine_id', $m->machine_id)->count());
        $this->assertTrue((bool) $cap->fresh()->activate);

        $pairs = ProdProcessMachine::count();
        foreach (['manual process' => $this->proc($this->manual), 'unknown process' => $this->proc(999999999), 'setup too big' => $this->proc($this->process, ['setup_minutes' => '70000'])] as $name => $row) {
            try {
                $svc->save($m, $this->in(['machine_name' => 'ZZ never']), [$row], [], $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('machines', $e->errors(), $name);
            }
        }
        $this->assertSame($pairs, ProdProcessMachine::count());
        $this->assertSame('ZZ Test Machine', $m->fresh()->machine_name, 'mesin ikut dibatalkan bila matriks salah');
    }

    public function test_downtime_log_open_close_and_locked_rows(): void
    {
        $svc = app(MachineMasterService::class);
        $m = $svc->save(null, $this->in(), [], [$this->stop(), $this->stop(['kind' => 'PLANNED_MAINT', 'start_date' => '2026-09-01', 'start_time' => '8:00', 'end_date' => '2026-09-01', 'end_time' => '10:30', 'cause' => '']), $this->stop(['cause' => '', 'start_date' => '', 'start_time' => ''])], $this->admin());
        $rows = ProdMachineDowntime::where('machine_id', $m->machine_id)->orderBy('start_at')->get();
        $this->assertCount(2, $rows, 'baris kosong dilewati');
        $this->assertTrue((bool) $rows[0]->is_planned, 'terencana ditentukan jenis');
        $this->assertSame(150, (int) $rows[0]->duration_minutes);
        $this->assertNull($rows[0]->root_cause);
        $open = $rows[1];
        $this->assertFalse((bool) $open->is_planned);
        $this->assertNull($open->end_at);
        $this->assertSame('ZZ jam', $open->root_cause);
        $this->assertSame($this->planRuns, PrdPlanRun::count(), 'hitung ulang rencana menunggu commit');
        $this->assertCount(2, $svc->downtimeRows($m));

        // Baris yang ada terkunci: jenis dan sebab tidak berubah, End menutup stop yang berjalan.
        $svc->save($m, $this->in(), [], [$this->stop(['downtime_id' => $open->downtime_id, 'kind' => 'POWER', 'cause' => 'changed', 'start_time' => '01:00', 'end_date' => '2026-10-01', 'end_time' => '09:00'])], $this->admin());
        $open->refresh();
        $this->assertSame('BREAKDOWN', $open->downtime_kind);
        $this->assertSame('ZZ jam', $open->root_cause);
        $this->assertSame('2026-10-01 07:30', $open->start_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-01 09:00', $open->end_at->format('Y-m-d H:i'));
        $this->assertSame(90, (int) $open->duration_minutes);

        // End sudah terisi tidak bisa diubah lagi.
        $svc->save($m, $this->in(), [], [$this->stop(['downtime_id' => $open->downtime_id, 'end_date' => '2026-10-02', 'end_time' => '09:00'])], $this->admin());
        $this->assertSame('2026-10-01 09:00', $open->fresh()->end_at->format('Y-m-d H:i'));

        $count = ProdMachineDowntime::count();
        $bad = ['no type' => $this->stop(['kind' => '']), 'no time' => $this->stop(['start_time' => '']), 'bad time' => $this->stop(['start_time' => '25:00']), 'bad date' => $this->stop(['start_date' => '01/10/2026']),
            'end before start' => $this->stop(['end_date' => '2026-09-30', 'end_time' => '07:00']), 'end date without time' => $this->stop(['end_date' => '2026-10-02']), 'cause too long' => $this->stop(['cause' => str_repeat('c', 256)]),
            'foreign stop' => $this->stop(['downtime_id' => (int) ProdMachineDowntime::where('machine_id', '!=', $m->machine_id)->value('downtime_id') ?: 999999999])];
        foreach ($bad as $name => $row) {
            try {
                $svc->save($m, $this->in(['machine_name' => 'ZZ never']), [], [$row], $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('downtimes', $e->errors(), $name);
            }
        }
        $this->assertSame($count, ProdMachineDowntime::count());
        $this->assertSame('ZZ Test Machine', $m->fresh()->machine_name);
    }

    public function test_oee_and_where_used_read_only(): void
    {
        $svc = app(MachineMasterService::class);
        $m = ProdMachine::where('machine_code', 'OFF-01')->firstOrFail();
        foreach ([7, 30, 90] as $days) {
            $o = $svc->oee($m, $days);
            $this->assertArrayHasKey('factors', $o['oee']);
            $this->assertArrayHasKey('mtbf_hours', $o['reliability']);
        }
        $this->assertArrayHasKey('Kapabilitas proses', $svc->whereUsed($m));
        $this->assertGreaterThan(0, $svc->processCount($m));
    }

    public function test_screens_routes_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'prod-machine-form');
        $this->get(route('v2.screen', 'prod-machine'))->assertOk();
        $this->get($form.'?new=1')->assertOk()->assertSee('name="machine_code"', false)->assertSee('Maintenance and OEE');
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();

        $this->post(route('v2.prod.machine.save'), $this->in(['processes' => [$this->proc($this->process)], 'downtimes' => [$this->stop(['end_date' => '2026-10-01', 'end_time' => '09:00'])]]))->assertRedirect();
        $m = ProdMachine::where('machine_name', 'ZZ Test Machine')->firstOrFail();
        $open = $form.'?id='.$m->machine_id;
        $this->get($open)->assertOk()->assertSee('ZZ Test Machine')->assertSee('name="max_speed_per_hour"', false)->assertSee('Machine stops');
        $this->get($form.'?id='.ProdMachine::where('machine_code', 'OFF-01')->value('machine_id'))->assertOk()->assertSee('Actual OEE');
        $this->post(route('v2.prod.machine.save'), $this->in(['id' => $m->machine_id, 'machine_name' => '']))->assertSessionHasErrors('machine_name');
        $this->post(route('v2.prod.machine.save'), $this->in(['id' => $m->machine_id, 'processes' => [$this->proc($this->manual)]]))->assertSessionHasErrors('machines');
        $this->post(route('v2.prod.machine.save'), ['id' => $m->machine_id, 'action' => 'deactivate'])->assertRedirect($open);
        $this->assertFalse((bool) $m->fresh()->activate);
        $this->post(route('v2.prod.machine.save'), ['id' => $m->machine_id, 'action' => 'activate'])->assertRedirect($open);
        $this->assertTrue((bool) $m->fresh()->activate);

        $grid = Grids::find('prod-machine');
        $this->assertStringContainsString('/v2/s/prod-machine-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/prod-machine-form?new=1', $grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/master/machine.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/master_machine_v1_2026-10-19.blade.php'));
        $this->assertArrayNotHasKey('PRE_MD_MACHINE', MenuRoutes::map());
        $this->assertArrayNotHasKey('MD_MACHINE', MenuRoutes::map());
        $this->get('/master/machine')->assertRedirect('/v2/s/prod-machine');
        $this->get('/master/machine?new=1')->assertRedirect('/v2/s/prod-machine-form?new=1');
        $this->get('/master/machine?open=35')->assertRedirect('/v2/s/prod-machine-form?id=35');
        $this->assertSame($this->planRuns, PrdPlanRun::count(), 'tes tidak memicu hitung ulang rencana');
    }
}
