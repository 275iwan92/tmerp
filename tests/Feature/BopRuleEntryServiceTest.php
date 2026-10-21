<?php

/*
 * REMARK
 * Tujuan       : Menjaga aturan BOP v2: simpan (jenis aturan, finishing wajib untuk finishing insert, proses kosong hanya untuk aturan bahan, label dan peran bahan wajib, rentang waste dan toleransi, kondisi hanya untuk standar, huruf besar merge group dan peran bahan), hanya admin yang boleh mengubah, aktif / nonaktif, usulan takaran (terapkan dan hitung ulang), layar, route, grid, redirect v1 dan menu. 26 aturan yang ada tidak disentuh.
 * Sumber hulu  : App\Services\Production\BopRuleEntryService, layar prod-bop-form, routes/v2_production.php, BopRuleGrid.
 * Input        : User johiwan (admin) dan satu user non-admin (dibuat di dalam tes); proses dan finishing real yang sudah ada (hanya dibaca). Seluruh uji berjalan di transaksi tm2mst yang di-rollback, jadi tidak ada data tersisa (hitung ulang usulan juga di dalam transaksi).
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=BopRuleEntryServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\ProdBopRule;
use App\Models\Master\ProdProcess;
use App\Models\Master\RefFinishingType;
use App\Models\Sys\SecUser;
use App\Services\Production\BopRuleEntryService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BopRuleEntryServiceTest extends TestCase
{
    private int $process;

    private int $finishing;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2mst')->beginTransaction();
        $this->process = (int) ProdProcess::where('activate', true)->orderBy('id_process')->value('id_process');
        $this->finishing = (int) RefFinishingType::where('activate', true)->orderBy('id_finishing_type')->value('id_finishing_type');
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
        return array_merge(['rule_scope' => 'STANDARD', 'finishing_type_id' => '', 'part_type' => '', 'step_seq' => '15', 'process_id' => $this->process, 'step_label' => 'ZZ step {machine}', 'merge_group' => 'zz grp', 'merge_label' => '+ ZZ',
            'machine_role' => 'PRINT', 'condition_code' => 'NEEDS_GLUE', 'material_pattern' => '', 'material_rule' => '', 'material_role' => '', 'usage_basis' => '', 'usage_rate' => '', 'waste_pct' => '1,5', 'tolerance_pct' => '0.5', 'note' => 'ZZ'], $over);
    }

    public function test_save_normalises_values_and_blanks_what_does_not_apply(): void
    {
        $svc = app(BopRuleEntryService::class);
        $before = ProdBopRule::count();
        $r = $svc->save(null, $this->in(), $this->admin());
        $this->assertSame($before + 1, ProdBopRule::count());
        $this->assertSame('ZZ GRP', $r->merge_group, 'merge group huruf besar');
        $this->assertSame(15, (int) $r->step_seq);
        $this->assertSame(1.5, (float) $r->waste_pct);
        $this->assertSame('NEEDS_GLUE', $r->condition_code);
        $this->assertTrue((bool) $r->activate);
        $this->assertNull($r->finishing_type_id);

        // Finishing insert: kondisi dikosongkan, finishing tersimpan.
        $f = $svc->save($r, $this->in(['rule_scope' => 'FINISHING', 'finishing_type_id' => $this->finishing]), $this->admin());
        $this->assertSame($this->finishing, (int) $f->finishing_type_id);
        $this->assertNull($f->condition_code);

        // Aturan bahan saja: tanpa proses, dengan pola, peran bahan huruf besar, takaran.
        $m = $svc->save(null, $this->in(['process_id' => '', 'step_label' => '', 'material_pattern' => 'ZZ%', 'material_role' => 'zz lem', 'material_rule' => 'BEST_STOCK', 'usage_basis' => 'PER_PCS', 'usage_rate' => '2,25', 'waste_pct' => '', 'tolerance_pct' => '']), $this->admin());
        $this->assertNull($m->process_id);
        $this->assertSame('ZZ LEM', $m->material_role);
        $this->assertSame(2.25, (float) $m->usage_rate);
        $this->assertNull($m->waste_pct);

        // auto_apply mengikuti isian; teks false dibaca salah.
        $this->assertTrue((bool) $svc->save($m, $this->in(['process_id' => '', 'step_label' => '', 'material_pattern' => 'ZZ%', 'material_role' => 'ZZ', 'auto_apply' => '1']), $this->admin())->auto_apply);
        $this->assertFalse((bool) $svc->save($m, $this->in(['process_id' => '', 'step_label' => '', 'material_pattern' => 'ZZ%', 'material_role' => 'ZZ', 'auto_apply' => 'false']), $this->admin())->auto_apply);
        $this->assertSame('Created', $svc->history($r->fresh())[count($svc->history($r->fresh())) - 1]['action']);
    }

    public function test_rules_are_refused_and_only_an_admin_may_change(): void
    {
        $svc = app(BopRuleEntryService::class);
        $before = ProdBopRule::count();
        $cases = [
            'bad scope' => $this->in(['rule_scope' => 'XX']),
            'finishing insert without finishing' => $this->in(['rule_scope' => 'FINISHING', 'finishing_type_id' => '']),
            'unknown finishing' => $this->in(['rule_scope' => 'FINISHING', 'finishing_type_id' => 999999999]),
            'bad part type' => $this->in(['part_type' => 'ZZ']),
            'blank seq' => $this->in(['step_seq' => '']),
            'seq too big' => $this->in(['step_seq' => '10000']),
            'no process and no pattern' => $this->in(['process_id' => '', 'step_label' => '']),
            'process without label' => $this->in(['step_label' => '']),
            'unknown process' => $this->in(['process_id' => 999999999]),
            'pattern without BOM line name' => $this->in(['process_id' => '', 'step_label' => '', 'material_pattern' => 'ZZ%', 'material_role' => '']),
            'bad machine role' => $this->in(['machine_role' => 'ZZ']),
            'bad condition' => $this->in(['condition_code' => 'ZZ']),
            'bad material rule' => $this->in(['material_rule' => 'ZZ']),
            'bad usage basis' => $this->in(['usage_basis' => 'ZZ']),
            'negative usage rate' => $this->in(['usage_rate' => '-1']),
            'waste over 100' => $this->in(['waste_pct' => '101']),
            'tolerance over 100' => $this->in(['tolerance_pct' => '100,5']),
            'note too long' => $this->in(['note' => str_repeat('n', 256)]),
        ];
        foreach ($cases as $name => $in) {
            try {
                $svc->save(null, $in, $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame($before, ProdBopRule::count(), 'penolakan tidak meninggalkan aturan');

        $plain = SecUser::where('is_admin', 0)->first() ?? SecUser::where('login', '!=', 'johiwan')->firstOrFail();
        $plain->is_admin = 0;
        $r = $svc->save(null, $this->in(), $this->admin());
        foreach ([fn () => $svc->save(null, $this->in(), $plain), fn () => $svc->save($r, $this->in(['step_label' => 'ZZ changed']), $plain), fn () => $svc->setActive($r, false, $plain),
            fn () => $svc->applySuggestion($r, $plain), fn () => $svc->refreshSuggestions($plain)] as $i => $attempt) {
            try {
                $attempt();
                $this->fail('bukan admin harus ditolak: '.$i);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('document', $e->errors());
            }
        }
        $this->assertSame('ZZ step {machine}', $r->fresh()->step_label);
        $this->assertTrue((bool) $r->fresh()->activate);
    }

    public function test_active_toggle_suggestions_and_refresh(): void
    {
        $svc = app(BopRuleEntryService::class);
        $r = $svc->save(null, $this->in(['process_id' => '', 'step_label' => '', 'material_pattern' => 'ZZ%', 'material_role' => 'ZZ', 'usage_rate' => '1']), $this->admin());
        $svc->setActive($r, false, $this->admin());
        $this->assertFalse((bool) $r->fresh()->activate);
        $svc->setActive($r, true, $this->admin());
        $this->assertTrue((bool) $r->fresh()->activate);

        try {
            $svc->applySuggestion($r, $this->admin());
            $this->fail('belum ada usulan');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }
        $r->update(['rate_suggested' => 3.5, 'waste_suggested' => 2.25]);
        $this->assertSame('Suggestion applied to this rule.', $svc->applySuggestion($r->fresh(), $this->admin()));
        $a = $r->fresh();
        $this->assertSame(3.5, (float) $a->usage_rate);
        $this->assertSame(2.25, (float) $a->waste_pct);
        $this->assertSame('AUTO', $a->rate_source);

        $this->assertNotSame('', $svc->refreshSuggestions($this->admin()));
    }

    public function test_screens_routes_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'prod-bop-form');
        $this->get(route('v2.screen', 'prod-bop-rule'))->assertOk();
        $this->get($form.'?new=1')->assertOk()->assertSee('name="step_seq"', false);
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();

        $this->post(route('v2.prod.bop.save'), $this->in())->assertRedirect();
        $r = ProdBopRule::where('step_label', 'ZZ step {machine}')->firstOrFail();
        $open = $form.'?id='.$r->id_bop_rule;
        $this->get($open)->assertOk()->assertSee('name="material_pattern"', false)->assertSee('name="auto_apply"', false);
        $this->post(route('v2.prod.bop.save'), $this->in(['id' => $r->id_bop_rule, 'step_label' => '']))->assertSessionHasErrors('step_label');
        $this->post(route('v2.prod.bop.save'), ['id' => $r->id_bop_rule, 'action' => 'deactivate'])->assertRedirect($open);
        $this->assertFalse((bool) $r->fresh()->activate);
        $this->post(route('v2.prod.bop.save'), ['id' => $r->id_bop_rule, 'action' => 'activate'])->assertRedirect($open);
        $this->post(route('v2.prod.bop.save'), ['id' => $r->id_bop_rule, 'action' => 'apply'])->assertSessionHasErrors('document');
        $this->post(route('v2.prod.bop.save'), ['id' => $r->id_bop_rule, 'action' => 'refresh'])->assertRedirect($open);

        $grid = Grids::find('prod-bop-rule');
        $this->assertStringContainsString('/v2/s/prod-bop-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/prod-bop-form?new=1', $grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/production/bop-rule.blade.php'));
        $this->assertArchived('production_bop_rule_v1_2026-10-19.blade.php');
        $this->assertArrayNotHasKey('PROD_MST_BOP_RULE', MenuRoutes::map());
        $this->get('/production/bop-rule')->assertRedirect('/v2/s/prod-bop-rule');
        $this->get('/production/bop-rule?new=1')->assertRedirect('/v2/s/prod-bop-form?new=1');
        $this->get('/production/bop-rule?open=4')->assertRedirect('/v2/s/prod-bop-form?id=4');
    }
}
