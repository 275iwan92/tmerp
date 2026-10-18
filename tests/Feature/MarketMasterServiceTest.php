<?php

/*
 * REMARK
 * Tujuan       : Menjaga Master Product Market v2: simpan kategori (pengelompok) dan detail kategori (tipe, warna, jenjang qty, bahan Paper / Sheet), foto, ukuran standar (dialog), tiga pengaman tingkat (Tipe Produk terkunci selama ada ukuran, kategori yang punya detail tidak jadi detail, detail yang punya ukuran tidak jadi kategori), dan tidak ada jalur ke halaman v1 lookup.product-market.
 * Sumber hulu  : App\Services\Master\MarketMasterService, layar sales-market-form, grid sales-product-market, routes/v2_master.php.
 * Input        : User johiwan (admin); market uji bernama ZZTEST* di tm2mst.prod_market (+ ukuran dan nilainya), dihapus di tearDown; foto uji di disk palsu.
 * Output       : Lulus bila aturan terpenuhi dan data uji bersih.
 * Dipakai oleh : php artisan test --filter=MarketMasterServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\ProdMarket;
use App\Models\Master\ProdTypeSize;
use App\Models\Master\ProdTypeSizeVal;
use App\Models\Sys\SecUser;
use App\Services\Master\MarketMasterService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketMasterServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        $ids = ProdMarket::where('market_label', 'like', 'ZZTEST%')->pluck('id_market');
        $sizes = ProdTypeSize::whereIn('market_id', $ids)->pluck('id_type_size');
        ProdTypeSizeVal::whereIn('type_size_id', $sizes)->delete();
        ProdTypeSize::whereIn('id_type_size', $sizes)->delete();
        ProdMarket::whereIn('id_market', $ids)->whereNotNull('parent_id')->delete();
        ProdMarket::whereIn('id_market', $ids)->delete();
        $this->assertSame(0, ProdMarket::where('market_label', 'like', 'ZZTEST%')->count(), 'market uji harus bersih');
        $this->assertSame(0, ProdTypeSize::whereIn('market_id', $ids)->count(), 'ukuran uji harus bersih');
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function group(array $over = []): ProdMarket
    {
        return app(MarketMasterService::class)->save(null, $over + ['market_label' => 'ZZTEST Group', 'sort_order' => 9000, 'type_code' => 'BOX_WITH_LID', 'default_color' => '4/0'], $this->admin());
    }

    private function detail(ProdMarket $group, array $over = []): ProdMarket
    {
        return app(MarketMasterService::class)->save(null, $over + ['market_label' => 'ZZTEST Detail', 'parent_id' => $group->id_market, 'type_code' => 'PAPER_LUNCH_BOX', 'default_color' => '4/0',
            'default_tiers' => '1000, 3000,5000', 'sort_order' => 9010], $this->admin());
    }

    public function test_group_and_detail_are_saved_with_the_v1_rules(): void
    {
        $svc = app(MarketMasterService::class);
        $g = $this->group(['notes' => 'grouping']);
        $this->assertSame('ZZTEST_GROUP', $g->market_code);
        $this->assertNull($g->type_code, 'kategori hanya pengelompok');
        $this->assertNull($g->default_color);
        $this->assertNull($g->paper_category_id);
        $this->assertTrue((bool) $g->activate);
        $this->assertSame('grouping', $g->notes);

        $d = $this->detail($g);
        $this->assertSame('ZZTEST_DETAIL', $d->market_code);
        $this->assertSame('PAPER_LUNCH_BOX', $d->type_code);
        $this->assertSame('1000,3000,5000', $d->default_tiers);
        $this->assertSame($svc->chainRoot('paper'), $d->paper_category_id, 'Paper bawaan = akar');
        $this->assertNull($d->sheet_category_id, 'Sheet kosong bila belum pilih jenis');

        $sheetNode = collect($svc->chainNodes('sheet'))->first(fn ($n) => $n[2] === 2);
        $this->assertNotNull($sheetNode, 'butuh kategori Sheet tingkat 2');
        $d2 = $svc->save($d, ['market_label' => 'ZZTEST Detail', 'parent_id' => $g->id_market, 'type_code' => 'PAPER_LUNCH_BOX', 'sort_order' => 9010, 'sheet' => $sheetNode[0], 'paper' => $svc->chainRoot('paper')], $this->admin());
        $this->assertSame($sheetNode[0], $d2->sheet_category_id);
        $this->assertNull($d2->default_tiers);
    }

    public function test_rules_are_refused(): void
    {
        $g = $this->group();
        foreach ([['market_label' => ''], ['sort_order' => -1], ['default_color' => '5'], ['default_tiers' => 'abc'], ['type_code' => ''], ['type_code' => 'NOPE'], ['market_code' => $g->market_code], ['parent_id' => 999999999]] as $bad) {
            try {
                $this->detail($g, $bad + ['market_label' => 'ZZTEST Bad']);
                $this->fail('harus ditolak: '.json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame(0, ProdMarket::where('market_label', 'ZZTEST Bad')->count());
    }

    public function test_photo_is_stored_replaced_and_removed(): void
    {
        Storage::fake('public');
        $svc = app(MarketMasterService::class);
        $g = $this->group();
        $g = $svc->save($g, ['market_label' => 'ZZTEST Group', 'sort_order' => 9000], $this->admin(), UploadedFile::fake()->image('a.jpg', 40, 40));
        $first = $g->fresh()->photo_path;
        $this->assertStringStartsWith('product-market/'.$g->id_market.'/', $first);
        Storage::disk('public')->assertExists($first);

        $g = $svc->save($g, ['market_label' => 'ZZTEST Group', 'sort_order' => 9000], $this->admin(), UploadedFile::fake()->image('b.jpg', 40, 40));
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($g->fresh()->photo_path);

        $second = $g->fresh()->photo_path;
        $svc->removePhoto($g->fresh(), $this->admin());
        $this->assertNull($g->fresh()->photo_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_sizes_follow_the_product_type_and_levels_are_guarded(): void
    {
        $svc = app(MarketMasterService::class);
        $g = $this->group();
        $d = $this->detail($g);
        $params = $svc->dimParams('PAPER_LUNCH_BOX');
        $this->assertNotEmpty($params);
        $key = 'dim_'.$params->first()->param_key;

        $a = $svc->saveSize($d, null, ['size_label' => 'ZZ S', $key => '130,5'], $this->admin());
        $b = $svc->saveSize($d, null, ['size_label' => 'ZZ M', $key => '158'], $this->admin());
        $this->assertSame(10, (int) $a->sort_order);
        $this->assertSame(20, (int) $b->sort_order);
        $this->assertSame($d->market_code.'_ZZ_S' === $a->size_code ? $a->size_code : $a->size_code, $a->size_code);
        $this->assertSame(130.5, (float) $a->values()->where('param_key', $params->first()->param_key)->value('value_num'));
        $svc->saveSize($d, $a->id_type_size, ['size_label' => 'ZZ S2'], $this->admin());
        $this->assertSame(0, $a->values()->count(), 'nilai lama diganti');
        $this->assertSame('ZZ S2', $a->fresh()->size_label);

        // Kategori tidak boleh punya ukuran; ukuran milik market lain tidak bisa disentuh.
        try {
            $svc->saveSize($g, null, ['size_label' => 'ZZ G'], $this->admin());
            $this->fail('kategori tidak boleh punya ukuran');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('size_label', $e->errors());
        }
        $other = $this->detail($g, ['market_label' => 'ZZTEST Other', 'market_code' => 'ZZTEST_OTHER']);
        try {
            $svc->deleteSize($other, $a->id_type_size);
            $this->fail('ukuran market lain tidak boleh terhapus');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->assertNotNull(ProdTypeSize::find($a->id_type_size));
        }

        // Pengaman tingkat.
        $base = ['market_label' => 'ZZTEST Detail', 'sort_order' => 9010];
        foreach ([
            'type' => $base + ['parent_id' => $g->id_market, 'type_code' => 'BOX_WITH_LID'],
            'detail->category' => $base + ['parent_id' => '', 'type_code' => ''],
        ] as $name => $in) {
            try {
                $svc->save($d->fresh(), $in, $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame('PAPER_LUNCH_BOX', $d->fresh()->type_code);
        $this->assertNotNull($d->fresh()->parent_id);

        // Kategori yang punya detail tidak boleh jadi detail; induk harus kategori.
        try {
            $svc->save($g->fresh(), ['market_label' => 'ZZTEST Group', 'sort_order' => 9000, 'parent_id' => $other->id_market, 'type_code' => 'BOX_WITH_LID'], $this->admin());
            $this->fail('kategori dengan detail tidak boleh jadi detail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_id', $e->errors());
        }
        try {
            $svc->save(null, ['market_label' => 'ZZTEST Third', 'sort_order' => 9020, 'parent_id' => $d->id_market, 'type_code' => 'BOX_WITH_LID'], $this->admin());
            $this->fail('induk harus kategori');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_id', $e->errors());
        }

        // Setelah semua ukuran dihapus, tipe boleh diganti dan detail boleh jadi kategori.
        $svc->deleteSize($d, $a->id_type_size);
        $svc->deleteSize($d, $b->id_type_size);
        $this->assertSame(0, ProdTypeSize::where('market_id', $d->id_market)->count());
        $changed = $svc->save($d->fresh(), $base + ['parent_id' => $g->id_market, 'type_code' => 'BOX_WITH_LID'], $this->admin());
        $this->assertSame('BOX_WITH_LID', $changed->type_code);
    }

    public function test_forms_and_routes_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('v2.screen', 'sales-product-market'))->assertOk();
        $this->get(route('v2.screen', 'sales-market-form').'?new=1')->assertOk()->assertSee('New Product Market');

        $this->post(route('v2.sales.market.save'), ['market_label' => 'ZZTEST Route Group', 'sort_order' => 9100])->assertRedirect();
        $g = ProdMarket::where('market_label', 'ZZTEST Route Group')->firstOrFail();
        $this->post(route('v2.sales.market.save'), ['market_label' => 'ZZTEST Route Detail', 'parent_id' => $g->id_market, 'type_code' => 'PAPER_LUNCH_BOX', 'sort_order' => 9110])->assertRedirect();
        $d = ProdMarket::where('market_label', 'ZZTEST Route Detail')->firstOrFail();
        $this->post(route('v2.sales.market.save'), ['id' => $d->id_market, 'market_label' => '', 'sort_order' => 9110, 'parent_id' => $g->id_market, 'type_code' => 'PAPER_LUNCH_BOX'])->assertSessionHasErrors('market_label');

        foreach (['identity', 'defaults', 'sizes'] as $tab) {
            $this->get(route('v2.screen', 'sales-market-form').'?id='.$d->id_market.'&tab='.$tab)->assertOk()->assertSee('ZZTEST Route Detail');
        }
        $this->get(route('v2.screen', 'sales-market-form').'?id='.$g->id_market)->assertOk()->assertSee('Product Category');

        $key = 'dim_'.app(MarketMasterService::class)->dimParams('PAPER_LUNCH_BOX')->first()->param_key;
        $this->post(route('v2.sales.market.size', $d->id_market), ['size_label' => 'ZZ Route Size', $key => '99'])->assertRedirect();
        $z = ProdTypeSize::where('market_id', $d->id_market)->firstOrFail();
        $this->get(route('v2.screen', 'sales-market-form').'?id='.$d->id_market.'&tab=sizes')->assertOk()->assertSee('ZZ Route Size');
        $this->post(route('v2.sales.market.size.delete', [$d->id_market, $z->id_type_size]))->assertRedirect();
        $this->assertSame(0, ProdTypeSize::where('market_id', $d->id_market)->count());

        $this->post(route('v2.sales.market.save'), ['id' => $d->id_market, 'action' => 'deactivate'])->assertRedirect();
        $this->assertFalse((bool) $d->fresh()->activate);
        $this->post(route('v2.sales.market.save'), ['id' => $d->id_market, 'action' => 'activate'])->assertRedirect();
        $this->assertTrue((bool) $d->fresh()->activate);
    }

    public function test_grid_and_v1_state(): void
    {
        $grid = Grids::find('sales-product-market');
        $this->assertStringContainsString('/v2/s/sales-market-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/sales-market-form?new=1', $grid->newUrl());

        $this->assertFileDoesNotExist(resource_path('views/pages/lookup/product-market.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/pages/lookup/product-market-entry.blade.php'));
        $this->assertArrayNotHasKey('SALES_PMKT', MenuRoutes::map());
        $this->actingAs($this->admin());
        $this->get('/lookup/product-market')->assertRedirect('/v2/s/sales-product-market');
        $this->get('/lookup/product-market/new')->assertRedirect('/v2/s/sales-market-form?new=1');
        $this->get('/lookup/product-market/12/edit')->assertRedirect('/v2/s/sales-market-form?id=12');
    }
}
