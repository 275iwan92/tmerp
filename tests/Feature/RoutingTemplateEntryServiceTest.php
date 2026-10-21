<?php

/*
 * REMARK
 * Tujuan       : Menjaga master Routing Template v2: simpan header dan semua langkah dalam satu Save (cabang, nomor urut, proses, mesin pilihan, opsional, qty factor, catatan), nomor urut unik per cabang, mesin pilihan harus mampu mengerjakan proses, nomor urut kosong = kelipatan 10 berikutnya, hasil pemeriksaan dan usulan mesin, aktif / nonaktif, layar, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Production\RoutingTemplateEntryService (+ RoutingTemplateService, MachineCapabilityService), layar prod-routing-form, routes/v2_production.php, RoutingTemplateGrid.
 * Input        : User johiwan (admin); proses dan mesin real yang sudah ada (hanya dibaca). Seluruh uji berjalan di transaksi tm2mst yang di-rollback, jadi tidak ada data tersisa.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=RoutingTemplateEntryServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdProcessMachine;
use App\Models\Master\ProdRoutingStep;
use App\Models\Master\ProdRoutingTemplate;
use App\Models\Sys\SecUser;
use App\Services\Production\RoutingTemplateEntryService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoutingTemplateEntryServiceTest extends TestCase
{
    private int $processA;

    private int $processB;

    private int $machineA;

    private int $machineOther;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2mst')->beginTransaction();
        $caps = ProdProcessMachine::where('activate', true)->orderBy('process_id')->get();
        $this->processA = (int) $caps->first()->process_id;
        $this->machineA = (int) $caps->first(fn ($c) => (int) $c->process_id === $this->processA)->machine_id;
        $this->processB = (int) $caps->first(fn ($c) => (int) $c->process_id !== $this->processA)->process_id;
        $this->machineOther = (int) ProdMachine::where('activate', true)->whereNotIn('machine_id', $caps->where('process_id', $this->processA)->pluck('machine_id'))->value('machine_id');
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
        return array_merge(['template_code' => 'zz-rt1', 'template_name' => 'ZZ routing test', 'prod_type_code' => 'BOX', 'substrate_hint' => 'ZZ duplex', 'description' => 'ZZ', 'is_default' => '1'], $over);
    }

    private function step(array $over = []): array
    {
        return array_merge(['branch_code' => 'MAIN', 'step_seq' => '10', 'process_id' => $this->processA, 'preferred_machine_id' => '', 'is_optional' => '', 'qty_factor' => '', 'note' => ''], $over);
    }

    public function test_save_writes_the_header_and_all_steps_and_assigns_missing_sequence_numbers(): void
    {
        $this->actingAs($this->admin());
        $svc = app(RoutingTemplateEntryService::class);
        $t = $svc->save(null, $this->in(), [[], $this->step(['step_seq' => '', 'preferred_machine_id' => $this->machineA, 'qty_factor' => '1,5', 'note' => 'ZZ note']), $this->step(['step_seq' => '', 'process_id' => $this->processB, 'is_optional' => '1']),
            $this->step(['branch_code' => 'lid', 'step_seq' => '5'])], $this->admin());
        $this->assertSame('ZZ-RT1', $t->template_code);
        $this->assertTrue((bool) $t->is_default);
        $this->assertTrue((bool) $t->activate);
        $steps = $t->steps()->get();
        $this->assertCount(3, $steps, 'baris kosong dibuang');
        $main = $steps->where('branch_code', 'MAIN')->values();
        $this->assertSame([10, 20], $main->pluck('step_seq')->map(fn ($v) => (int) $v)->all(), 'nomor kosong = kelipatan 10 berikutnya');
        $this->assertSame($this->machineA, (int) $main[0]->preferred_machine_id);
        $this->assertSame(1.5, (float) $main[0]->qty_factor);
        $this->assertSame('ZZ note', $main[0]->note);
        $this->assertTrue((bool) $main[1]->is_optional);
        $this->assertSame(['LID' => 5], $steps->where('branch_code', 'LID')->mapWithKeys(fn ($s) => [$s->branch_code => (int) $s->step_seq])->all());

        // Tombol Ya/Tidak pada baris terkirim sebagai teks "true" / "false".
        $flags = $svc->save(null, $this->in(['template_code' => 'ZZ-BOOL']), [$this->step(['is_optional' => 'false']), $this->step(['step_seq' => '20', 'is_optional' => 'true'])], $this->admin())->steps()->get();
        $this->assertSame([false, true], $flags->pluck('is_optional')->map(fn ($v) => (bool) $v)->all());

        // Simpan ulang mengganti seluruh langkah.
        $u = $svc->save($t, $this->in(['template_name' => 'ZZ renamed', 'is_default' => '']), [$this->step()], $this->admin());
        $this->assertSame('ZZ renamed', $u->template_name);
        $this->assertFalse((bool) $u->is_default);
        $this->assertSame(1, $u->steps()->count());
        $rows = $svc->rows($u);
        $this->assertSame($this->processA, (int) $rows[0]['process_id']);
        $this->assertNotSame('', $rows[0]['process_id_label']);

        // Template tanpa langkah sah.
        $this->assertSame(0, $svc->save(null, $this->in(['template_code' => 'ZZ-RT2']), [], $this->admin())->steps()->count());

        $res = $svc->result($u->fresh());
        $this->assertArrayHasKey('ok', $res['check']);
        $this->assertCount(1, $res['expansion']);
        $svc->setActive($u, false, $this->admin());
        $this->assertFalse((bool) $u->fresh()->activate);
        $this->assertSame('Created', $svc->history($u->fresh())[count($svc->history($u->fresh())) - 1]['action']);
    }

    public function test_rules_are_refused_and_nothing_is_left_behind(): void
    {
        $svc = app(RoutingTemplateEntryService::class);
        $svc->save(null, $this->in(['template_code' => 'ZZ-TAKEN']), [], $this->admin());
        $cases = [
            'duplicate code' => [$this->in(['template_code' => 'zz-taken']), []],
            'blank code' => [$this->in(['template_code' => '']), []],
            'bad code' => [$this->in(['template_code' => 'ZZ!']), []],
            'code too long' => [$this->in(['template_code' => str_repeat('A', 21)]), []],
            'blank name' => [$this->in(['template_name' => '']), []],
            'step without process' => [$this->in(), [$this->step(['process_id' => '', 'step_seq' => '10'])]],
            'unknown process' => [$this->in(), [$this->step(['process_id' => 999999999])]],
            'bad branch' => [$this->in(), [$this->step(['branch_code' => 'a b!'])]],
            'sequence 0' => [$this->in(), [$this->step(['step_seq' => '0'])]],
            'sequence text' => [$this->in(), [$this->step(['step_seq' => 'x'])]],
            'sequence twice in a branch' => [$this->in(), [$this->step(), $this->step(['process_id' => $this->processB])]],
            'machine not capable' => [$this->in(), [$this->step(['preferred_machine_id' => $this->machineOther])]],
            'qty factor too big' => [$this->in(), [$this->step(['qty_factor' => '100'])]],
            'note too long' => [$this->in(), [$this->step(['note' => str_repeat('n', 256)])]],
        ];
        $before = ProdRoutingTemplate::count();
        foreach ($cases as $name => [$in, $steps]) {
            try {
                $svc->save(null, $in, $steps, $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame($before, ProdRoutingTemplate::count(), 'penolakan tidak meninggalkan template');
        // Sama di cabang lain sah.
        $t = $svc->save(null, $this->in(['template_code' => 'ZZ-OK']), [$this->step(), $this->step(['branch_code' => 'BODY'])], $this->admin());
        $this->assertSame(2, $t->steps()->count());
        // Mesin yang sudah tersimpan tidak diperiksa ulang bila pasangannya tidak berubah.
        ProdRoutingStep::where('template_id', $t->template_id)->update(['preferred_machine_id' => $this->machineOther]);
        $again = $svc->save($t, $this->in(['template_code' => 'ZZ-OK']), [$this->step(['preferred_machine_id' => $this->machineOther])], $this->admin());
        $this->assertSame($this->machineOther, (int) $again->steps()->first()->preferred_machine_id);
    }

    public function test_screens_routes_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'prod-routing-form');
        $this->get(route('v2.screen', 'prod-routing'))->assertOk();
        $this->get($form.'?new=1')->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();

        $in = $this->in(['steps' => [$this->step()]]);
        $this->post(route('v2.prod.routing.save'), $in)->assertRedirect();
        $t = ProdRoutingTemplate::where('template_code', 'ZZ-RT1')->firstOrFail();
        $open = $form.'?id='.$t->template_id;
        $this->get($open)->assertOk()->assertSee('data-rows', false);
        $this->post(route('v2.prod.routing.save'), array_merge($in, ['id' => $t->template_id, 'template_name' => '']))->assertSessionHasErrors('template_name');
        $this->post(route('v2.prod.routing.save'), array_merge($in, ['id' => $t->template_id, 'steps' => [$this->step(), $this->step()]]))->assertSessionHasErrors('steps');
        $this->post(route('v2.prod.routing.save'), ['id' => $t->template_id, 'action' => 'deactivate'])->assertRedirect($open);
        $this->assertFalse((bool) $t->fresh()->activate);
        $this->post(route('v2.prod.routing.save'), ['id' => $t->template_id, 'action' => 'activate'])->assertRedirect($open);

        $grid = Grids::find('prod-routing');
        $this->assertStringContainsString('/v2/s/prod-routing-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/prod-routing-form?new=1', $grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/production/routing-template.blade.php'));
        $this->assertArchived('production_routing_template_v1_2026-10-19.blade.php');
        $this->assertArrayNotHasKey('PROD_ROUTING_TPL', MenuRoutes::map());
        $this->get('/production/routing-template')->assertRedirect('/v2/s/prod-routing');
        $this->get('/production/routing-template?new=1')->assertRedirect('/v2/s/prod-routing-form?new=1');
        $this->get('/production/routing-template?open=4')->assertRedirect('/v2/s/prod-routing-form?id=4');
    }
}
