<?php

/*
 * REMARK
 * Tujuan       : Menjaga Master Raw Material v2: simpan item (kode dari rantai kategori + angka urut bila kembar, aturan wajib / angka / pilihan), item lama tanpa kategori tetap utuh, Confirm (3 akun wajib, mengunci kategori dan kode), aktif / nonaktif, Delete (hapus bila bersih, nonaktif bila ada stok atau masih dipakai).
 * Sumber hulu  : App\Services\Master\MaterialMasterService, MaterialGuardService, MaterialCategoryService, layar md-raw-form, grid md-raw, routes/v2_master.php.
 * Input        : User johiwan (admin); item uji bernama ZZTEST* di tm2mst.mat_raw, dihapus di tearDown; uji Delete pada item yang sudah dipakai berjalan di transaksi yang di-rollback.
 * Output       : Lulus bila aturan terpenuhi dan tidak ada jalur ke halaman v1 master.raw-material.
 * Dipakai oleh : php artisan test --filter=MaterialMasterServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\MatRaw;
use App\Models\Sys\SecUser;
use App\Services\Master\MaterialCategoryService;
use App\Services\Master\MaterialGuardService;
use App\Services\Master\MaterialMasterService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaterialMasterServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        while (DB::connection('tm2mst')->transactionLevel() > 0) {
            DB::connection('tm2mst')->rollBack();
        }
        MatRaw::where('material_name', 'like', 'ZZTEST%')->delete();
        $this->assertSame(0, MatRaw::where('material_name', 'like', 'ZZTEST%')->count(), 'item uji harus bersih');
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    /** id rantai kategori tingkat 2 yang utuh (tingkat 1 + 2) */
    private function chain(int $skip = 0): int
    {
        $nodes = array_values(array_filter(app(MaterialCategoryService::class)->nodes(), fn ($n) => $n[2] === 2));
        $this->assertNotEmpty($nodes, 'butuh rantai kategori tingkat 2');

        return $nodes[$skip][0];
    }

    private function accounts(): array
    {
        $ids = DB::connection('tm2acc')->table('fin_account')->where('is_postable', 1)->orderBy('account_id')->limit(3)->pluck('account_id')->all();

        return ['stock_asset_account_id' => $ids[0], 'cogs_account_id' => $ids[1], 'gr_ir_account_id' => $ids[2]];
    }

    private function input(array $over = []): array
    {
        return $over + ['material_name' => 'ZZTEST Item', 'item_type' => 'MATERIAL', 'category' => $this->chain(), 'purchasable' => '1', 'qc_required' => '0', 'is_fx_sensitive' => '0',
            'gram_weight' => '210', 'moisture_pct' => '7,5', 'conversion_to_stock' => '1', 'purchase_tax_code' => 'PPN', 'costing_method' => 'AVG', 'uom_stock' => 'KG', 'uom_purchase' => 'KG'];
    }

    public function test_item_is_saved_with_code_from_category_chain_and_running_number(): void
    {
        $svc = app(MaterialMasterService::class);
        $cat = app(MaterialCategoryService::class);
        $chain = $this->chain();
        $base = $cat->code($chain);

        $a = $svc->save(null, $this->input(), $this->admin());
        $this->assertMatchesRegularExpression('/^'.preg_quote($base, '/').'\d*$/', $a->material_code);
        $this->assertSame($cat->names($chain), array_values(array_filter([$a->category1, $a->category2, $a->category3, $a->category4, $a->category5])));
        $this->assertTrue((bool) $a->activate);
        $this->assertFalse((bool) $a->stat_confirm);
        $this->assertTrue((bool) $a->purchasable);
        $this->assertSame(210.0, (float) $a->gram_weight);
        $this->assertSame(7.5, (float) $a->moisture_pct);
        $this->assertSame('johiwan', $a->create_by);

        $b = $svc->save(null, $this->input(['material_name' => 'ZZTEST Twin']), $this->admin());
        $this->assertNotSame($a->material_code, $b->material_code);
        $this->assertStringStartsWith($base, $b->material_code);

        // rantai sama -> kode tetap; rantai lain (Draft) -> kode dibentuk ulang
        $again = $svc->save($a, $this->input(['material_name' => 'ZZTEST Renamed']), $this->admin());
        $this->assertSame($a->material_code, $again->fresh()->material_code);
        $other = $this->chain(1);
        $moved = $svc->save($a, $this->input(['category' => $other]), $this->admin());
        $this->assertStringStartsWith($cat->code($other), $moved->fresh()->material_code);
    }

    public function test_rules_are_refused_and_legacy_items_stay_intact(): void
    {
        $svc = app(MaterialMasterService::class);
        $user = $this->admin();
        foreach ([['material_name' => ''], ['item_type' => 'BOGUS'], ['category' => ''], ['moisture_pct' => '120'], ['conversion_to_stock' => '-1'], ['costing_method' => 'XX'], ['suggest_supplier_id' => '999999999']] as $bad) {
            try {
                $svc->save(null, $this->input($bad), $user);
                $this->fail('harus ditolak: '.json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }

        // Item lama tanpa kategori: simpan tanpa mengubah rantai tidak menyentuh kode / kategori.
        $legacy = MatRaw::create(['material_name' => 'ZZTEST Legacy', 'material_code' => 'ZZTESTLEG1', 'item_type' => 'MATERIAL', 'activate' => true, 'create_by' => 'test']);
        $saved = $svc->save($legacy, $this->input(['category' => '', 'material_name' => 'ZZTEST Legacy 2']), $user);
        $this->assertSame('ZZTESTLEG1', $saved->fresh()->material_code);
        $this->assertSame('ZZTEST Legacy 2', $saved->fresh()->material_name);

        // Item dengan kategori: mengosongkan rantai ditolak (v1 menghapus nama kategori diam-diam).
        $item = $svc->save(null, $this->input(), $user);
        $this->expectException(ValidationException::class);
        $svc->save($item, $this->input(['category' => '']), $user);
    }

    public function test_confirm_needs_three_accounts_and_locks_category_and_code(): void
    {
        $svc = app(MaterialMasterService::class);
        $user = $this->admin();
        $item = $svc->save(null, $this->input(), $user);
        $code = $item->material_code;

        try {
            $svc->confirm($item, $this->input(), $user);
            $this->fail('Confirm tanpa akun harus ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('confirm', $e->errors());
        }
        $this->assertFalse((bool) $item->fresh()->stat_confirm);

        $done = $svc->confirm($item, $this->input($this->accounts()), $user);
        $this->assertTrue((bool) $done->fresh()->stat_confirm);
        $this->assertSame('johiwan', $done->fresh()->confirmed_by);

        $after = $svc->save($done->fresh(), $this->input(['category' => $this->chain(1), 'material_name' => 'ZZTEST Confirmed'] + $this->accounts()), $user);
        $this->assertSame($code, $after->fresh()->material_code);
        $this->assertSame('ZZTEST Confirmed', $after->fresh()->material_name);
    }

    public function test_delete_removes_a_clean_item_and_only_deactivates_a_used_one(): void
    {
        $svc = app(MaterialMasterService::class);
        $user = $this->admin();
        $clean = $svc->save(null, $this->input(), $user);
        $this->assertSame([], app(MaterialGuardService::class)->blockingReasons($clean->material_id));
        $this->assertSame([], $svc->delete($clean, $user));
        $this->assertNull(MatRaw::find($clean->material_id));

        $stocked = $svc->save(null, $this->input(['material_name' => 'ZZTEST Stocked']), $user);
        $stocked->update(['cur_stock' => 5]);
        $this->assertSame(['stock on hand'], $svc->delete($stocked->fresh(), $user));
        $this->assertFalse((bool) MatRaw::find($stocked->material_id)->activate);

        $usedId = DB::connection('tm2pur')->table('po_line')->whereNotNull('item_id')->value('item_id');
        $used = $usedId ? MatRaw::where('activate', true)->find($usedId) : null;
        if (! $used) {
            $this->markTestSkipped('No active item used by a purchase order line in this database.');
        }
        DB::connection('tm2mst')->beginTransaction();
        $reasons = $svc->delete($used, $user);
        $this->assertContains('purchase documents', $reasons);
        $this->assertNotNull(MatRaw::find($used->material_id));
        $this->assertFalse((bool) MatRaw::find($used->material_id)->activate);
        DB::connection('tm2mst')->rollBack();
        $this->assertTrue((bool) MatRaw::find($used->material_id)->activate);
    }

    public function test_forms_and_routes_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('v2.screen', 'md-raw'))->assertOk();
        $this->get(route('v2.screen', 'md-raw-form').'?new=1')->assertOk()->assertSee('New Raw Material');

        $this->post(route('v2.md.raw.save'), $this->input(['material_name' => 'ZZTEST Route']))->assertRedirect();
        $m = MatRaw::where('material_name', 'ZZTEST Route')->firstOrFail();
        foreach (['category', 'specs', 'uom', 'inventory', 'accounting'] as $tab) {
            $this->get(route('v2.screen', 'md-raw-form').'?id='.$m->material_id.'&tab='.$tab)->assertOk()->assertSee('ZZTEST Route')->assertSee('Confirm')->assertSee('Delete');
        }
        $this->post(route('v2.md.raw.save'), $this->input(['id' => $m->material_id, 'material_name' => '']))->assertSessionHasErrors('material_name');

        $this->post(route('v2.md.raw.save'), $this->input(['id' => $m->material_id, 'action' => 'confirm', 'material_name' => 'ZZTEST Route']))->assertSessionHasErrors('confirm');
        $this->post(route('v2.md.raw.save'), $this->input(['id' => $m->material_id, 'action' => 'confirm', 'material_name' => 'ZZTEST Route'] + $this->accounts()))->assertRedirect();
        $this->assertTrue((bool) $m->fresh()->stat_confirm);
        $this->get(route('v2.screen', 'md-raw-form').'?id='.$m->material_id)->assertOk()->assertSee('Confirmed');

        $this->post(route('v2.md.raw.save'), ['id' => $m->material_id, 'action' => 'deactivate'])->assertRedirect();
        $this->assertFalse((bool) $m->fresh()->activate);
        $this->post(route('v2.md.raw.save'), ['id' => $m->material_id, 'action' => 'delete'])->assertRedirect(route('v2.screen', 'md-raw'));
        $this->assertNull(MatRaw::find($m->material_id));
    }

    public function test_grid_and_v1_state(): void
    {
        $grid = Grids::find('md-raw');
        $this->assertStringContainsString('/v2/s/md-raw-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/md-raw-form?new=1', $grid->newUrl());

        $this->assertDirectoryDoesNotExist(resource_path('views/pages/master/raw-material'));
        foreach (['PRE_MD_RAW', 'MD_RAW', 'INV_REF_RAW'] as $code) {
            $this->assertArrayNotHasKey($code, MenuRoutes::map());
        }
        $this->actingAs($this->admin());
        $this->get('/master/raw-material')->assertRedirect('/v2/s/md-raw');
        $this->get('/master/raw-material/create')->assertRedirect('/v2/s/md-raw-form?new=1');
        $this->get('/master/raw-material/123/edit')->assertRedirect('/v2/s/md-raw-form?id=123');
    }
}
