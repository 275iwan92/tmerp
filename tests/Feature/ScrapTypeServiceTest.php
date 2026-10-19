<?php

/*
 * REMARK
 * Tujuan       : Menjaga master Tipe Scrap v2: simpan (kode dinormalkan, unik per kategori, Sort order), kategori dan kode terkunci bila sudah dipakai di catatan output produksi, aktif / nonaktif, layar, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Production\ScrapTypeService, layar prod-scrap-form, routes/v2_production.php, ScrapTypeGrid.
 * Input        : User johiwan (admin). Seluruh uji berjalan di transaksi tm2prd yang di-rollback, jadi tidak ada data tersisa.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=ScrapTypeServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Prd\PrdScrapType;
use App\Models\Sys\SecUser;
use App\Services\Production\ScrapTypeService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScrapTypeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2prd')->beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::connection('tm2prd')->transactionLevel() > 0) {
            DB::connection('tm2prd')->rollBack();
        }
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function in(array $over = []): array
    {
        return array_merge(['category' => 'REJECT', 'type_code' => 'zz test code', 'type_name' => 'ZZ Test type', 'sort_order' => ''], $over);
    }

    private function use(PrdScrapType $t): void
    {
        $c = DB::connection('tm2prd');
        $c->statement('SET FOREIGN_KEY_CHECKS=0');
        $c->table('prd_wo_operation_output_defect')->insert(['output_id' => 999999999, 'scrap_type_id' => $t->type_id, 'qty' => 1]);
        $c->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_save_normalises_the_code_and_defaults_the_sort_order(): void
    {
        $svc = app(ScrapTypeService::class);
        $t = $svc->save(null, $this->in(), $this->admin());
        $this->assertSame('ZZ_TEST_CODE', $t->type_code);
        $this->assertSame(500, (int) $t->sort_order);
        $this->assertTrue((bool) $t->activate);
        $this->assertSame('johiwan', $t->created_by);

        $u = $svc->save($t, $this->in(['type_name' => 'ZZ Renamed', 'sort_order' => '25', 'category' => 'WASTE']), $this->admin());
        $this->assertSame('ZZ Renamed', $u->type_name);
        $this->assertSame(25, (int) $u->sort_order);
        $this->assertSame('WASTE', $u->category, 'tipe yang belum dipakai boleh pindah kategori');
        $this->assertSame(25, (int) $svc->save($u, $this->in(['category' => 'WASTE', 'sort_order' => '']), $this->admin())->sort_order, 'Sort order kosong tidak mengubah yang ada');
    }

    public function test_code_is_unique_within_the_category_and_other_rules_are_refused(): void
    {
        $svc = app(ScrapTypeService::class);
        $svc->save(null, $this->in(), $this->admin());
        $svc->save(null, $this->in(['category' => 'WASTE']), $this->admin());
        $this->assertSame(2, PrdScrapType::where('type_code', 'ZZ_TEST_CODE')->count(), 'kode yang sama boleh di kategori lain');

        $cases = [
            'same code and category' => $this->in(),
            'blank name' => $this->in(['type_code' => 'ZZ2', 'type_name' => '']),
            'blank code' => $this->in(['type_code' => '']),
            'bad category' => $this->in(['type_code' => 'ZZ3', 'category' => 'XX']),
            'code too long' => $this->in(['type_code' => str_repeat('A', 31)]),
            'bad characters' => $this->in(['type_code' => 'ZZ4!']),
            'bad sort order' => $this->in(['type_code' => 'ZZ5', 'sort_order' => 'abc']),
        ];
        foreach ($cases as $name => $in) {
            try {
                $svc->save(null, $in, $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        // Mengubah ke kode milik tipe lain di kategori yang sama ditolak.
        $other = $svc->save(null, $this->in(['type_code' => 'ZZ_OTHER']), $this->admin());
        try {
            $svc->save($other, $this->in(['type_code' => 'ZZ_TEST_CODE']), $this->admin());
            $this->fail('kode bentrok');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('type_code', $e->errors());
        }
        $this->assertSame('ZZ_OTHER', $other->fresh()->type_code);
    }

    public function test_a_type_used_in_output_records_keeps_its_category_and_code(): void
    {
        $svc = app(ScrapTypeService::class);
        $t = $svc->save(null, $this->in(), $this->admin());
        $this->assertSame(0, $svc->usage($t));
        $this->use($t);
        $this->assertSame(1, $svc->usage($t));

        foreach ([['category' => 'WASTE'], ['type_code' => 'ZZ_CHANGED']] as $over) {
            try {
                $svc->save($t, $this->in($over), $this->admin());
                $this->fail('kategori dan kode terkunci');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('type_code', $e->errors());
            }
        }
        // Nama dan Sort order tetap boleh; kategori dan kode yang tidak terkirim dianggap tidak berubah.
        $u = $svc->save($t, ['type_name' => 'ZZ Name only', 'sort_order' => '77'], $this->admin());
        $this->assertSame('ZZ Name only', $u->type_name);
        $this->assertSame('REJECT', $u->category);
        $this->assertSame('ZZ_TEST_CODE', $u->type_code);
        $this->assertSame(77, (int) $u->sort_order);

        $svc->setActive($u, false, $this->admin());
        $this->assertFalse((bool) $u->fresh()->activate);
        $svc->setActive($u, true, $this->admin());
        $this->assertTrue((bool) $u->fresh()->activate);
        $this->assertSame('Created', $svc->history($u->fresh())[count($svc->history($u->fresh())) - 1]['action']);
    }

    public function test_screens_routes_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'prod-scrap-form');
        $this->get(route('v2.screen', 'prod-scrap'))->assertOk();
        $this->get($form.'?new=1')->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();

        $this->post(route('v2.prod.scrap.save'), $this->in())->assertRedirect();
        $t = PrdScrapType::where('type_code', 'ZZ_TEST_CODE')->firstOrFail();
        $open = $form.'?id='.$t->type_id;
        $this->get($open)->assertOk()->assertSee('name="category"', false)->assertSee('name="type_code"', false);
        $this->post(route('v2.prod.scrap.save'), $this->in(['id' => $t->type_id, 'type_name' => '']))->assertSessionHasErrors('type_name');
        $this->post(route('v2.prod.scrap.save'), ['id' => $t->type_id, 'action' => 'deactivate'])->assertRedirect($open);
        $this->assertFalse((bool) $t->fresh()->activate);
        $this->post(route('v2.prod.scrap.save'), ['id' => $t->type_id, 'action' => 'activate'])->assertRedirect($open);
        $this->assertTrue((bool) $t->fresh()->activate);

        $this->use($t);
        $this->get($open)->assertOk()->assertDontSee('name="type_code"', false)->assertSee('name="type_name"', false);

        $grid = Grids::find('prod-scrap');
        $this->assertStringContainsString('/v2/s/prod-scrap-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/prod-scrap-form?new=1', $grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/production/scrap-type.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/production_scrap_type_v1_2026-10-19.blade.php'));
        $this->assertArrayNotHasKey('PROD_SCRAP_TYPE', MenuRoutes::map());
        $this->get('/production/scrap-type')->assertRedirect('/v2/s/prod-scrap');
        $this->get('/production/scrap-type?new=1')->assertRedirect('/v2/s/prod-scrap-form?new=1');
        $this->get('/production/scrap-type?open=3')->assertRedirect('/v2/s/prod-scrap-form?id=3');
    }
}
