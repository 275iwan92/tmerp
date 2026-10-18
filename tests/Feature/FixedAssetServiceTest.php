<?php

/*
 * REMARK
 * Tujuan       : Menjaga aset tetap v2: simpan draft (kode, tanggal review, isian kategori), aturan nilai (residu < biaya, umur, metode), pemetaan status, penolakan ubah aset yang sudah Active, Depreciation Run (pratinjau tanpa menulis, periode tidak sah ditolak), form / run / grid terbuka, menu ACC_DEPR_RUN, v1 sudah pindah ke arsip.
 * Sumber hulu  : App\Services\Acc\FixedAssetService, AssetService, layar acc-fixed-asset-form dan acc-depreciation-run, routes/v2_finance.php.
 * Input        : User johiwan (admin); aset uji bernama ZZTEST* di tm2acc.fin_asset (dihapus di tearDown); aset Active yang sudah ada hanya dibaca. Tes tidak mengkapitalisasi, tidak melepas, dan tidak menjalankan penyusutan ke GL.
 * Output       : Lulus bila aturan terpenuhi dan tidak ada jalur ke halaman v1 finance.assets.
 * Dipakai oleh : php artisan test --filter=FixedAssetServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAsset;
use App\Models\Acc\FinAssetCategory;
use App\Models\Acc\FinAssetDepr;
use App\Models\Sys\SecUser;
use App\Services\Acc\FixedAssetService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FixedAssetServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        FinAsset::withoutGlobalScopes()->where('asset_name', 'like', 'ZZTEST%')->delete();
        $this->assertSame(0, FinAsset::withoutGlobalScopes()->where('asset_name', 'like', 'ZZTEST%')->count(), 'aset uji harus bersih');
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function category(): FinAssetCategory
    {
        return FinAssetCategory::where('asset_type', 'FIXED')->where('activate', true)->orderBy('category_id')->firstOrFail();
    }

    private function input(array $over = []): array
    {
        $c = $this->category();

        return $over + ['asset_name' => 'ZZTEST asset', 'category_id' => $c->category_id, 'acq_date' => '2026-10-15', 'start_date' => '2026-10-01', 'cost' => 120000000, 'residual' => 0,
            'life' => 48, 'method' => 'SL', 'credit_account_id' => (int) FinAccount::where('account_code', '211900')->value('account_id'), 'location' => 'ZZTEST lab'];
    }

    public function test_draft_asset_gets_code_review_date_and_defaults_from_category(): void
    {
        $svc = app(FixedAssetService::class);
        $a = $svc->save(null, $this->input(), $this->admin());

        $this->assertSame('DRAFT', $a->status);
        $this->assertMatchesRegularExpression('/^FA-'.$this->category()->category_code.'-2026-\d{4}$/', $a->asset_code);
        $this->assertSame('2027-10-15', $a->next_review_date->toDateString());
        $this->assertSame(['draft', null], $svc->formState($a));
        $this->assertNull($a->capitalize_voucher_id);

        $def = $svc->defaults($this->category());
        $this->assertSame((int) ($this->category()->useful_life_months ?: 48), $def['life']);
        $this->assertSame($this->category()->method ?: 'SL', $def['method']);

        $a2 = $svc->save($a, $this->input(['asset_name' => 'ZZTEST renamed', 'cost' => '150.000.000,00', 'life' => 60]), $this->admin());
        $this->assertSame($a->asset_code, $a2->asset_code);
        $this->assertSame(150000000.0, (float) $a2->acquisition_cost);
        $this->assertSame(60, (int) $a2->useful_life_months);
    }

    public function test_value_rules_are_refused_and_active_assets_cannot_be_changed(): void
    {
        $svc = app(FixedAssetService::class);
        $user = $this->admin();
        foreach ([$this->input(['cost' => 0]), $this->input(['residual' => 120000000]), $this->input(['life' => 0]), $this->input(['method' => 'XX']), $this->input(['asset_name' => ''])] as $bad) {
            try {
                $svc->save(null, $bad, $user);
                $this->fail('input must be refused');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $active = FinAsset::where('status', 'ACTIVE')->first();
        if (! $active) {
            $this->markTestSkipped('No active asset in this database.');
        }
        $before = $active->only(['asset_name', 'acquisition_cost']);
        try {
            $svc->save($active, $this->input(), $user);
            $this->fail('an active asset must not be changed');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame($before, $active->fresh()->only(['asset_name', 'acquisition_cost']));
    }

    public function test_form_state_mapping_and_dispose_guards(): void
    {
        $svc = app(FixedAssetService::class);
        $this->assertSame(['confirmed', 'Active'], $svc->formState(new FinAsset(['status' => 'ACTIVE'])));
        $this->assertSame(['confirmed', 'Fully depreciated'], $svc->formState(new FinAsset(['status' => 'FULLY_DEPRECIATED'])));
        $this->assertSame(['closed', 'Disposed'], $svc->formState(new FinAsset(['status' => 'DISPOSED'])));

        $draft = $svc->save(null, $this->input(), $this->admin());
        $bank = (int) FinAccount::where('account_code', 'like', '111%')->where('is_postable', true)->value('account_id');
        $this->expectException(HttpException::class);
        $svc->dispose($draft, ['disp_date' => '2026-10-31', 'disp_amount' => 0, 'disp_bank' => $bank], $this->admin());
    }

    public function test_depreciation_run_preview_writes_nothing_and_bad_period_is_refused(): void
    {
        $svc = app(FixedAssetService::class);
        $before = [FinAssetDepr::withoutGlobalScopes()->count(), DB::connection('tm2acc')->table('fin_voucher')->count()];

        $preview = $svc->runPreview(now()->format('Y-m'), 'fixed');
        foreach ($preview as $row) {
            $this->assertGreaterThan(0, $row->amount);
            $this->assertSame('ACTIVE', $row->asset->status);
        }
        $this->assertSame($before, [FinAssetDepr::withoutGlobalScopes()->count(), DB::connection('tm2acc')->table('fin_voucher')->count()]);
        $this->assertNotEmpty($svc->periodOptions());
        $this->assertSame(['FIXED'], FixedAssetService::RUN_KINDS['fixed']);

        $this->expectException(HttpException::class);
        $svc->runPreview('2026-13', 'fixed');
    }

    public function test_forms_run_screen_and_save_route_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        $c = $this->category();
        $this->get(route('v2.screen', 'acc-fixed-asset'))->assertOk();
        $this->get(route('v2.screen', 'acc-fixed-asset-form').'?new=1')->assertOk()->assertSee('Start a fixed asset');
        $this->get(route('v2.screen', 'acc-fixed-asset-form').'?category='.$c->category_id)->assertOk()->assertSee('New Fixed Asset');
        $this->get(route('v2.screen', 'acc-depreciation-run'))->assertOk()->assertSee('Depreciation Run');
        $this->get(route('v2.screen', 'acc-depreciation-run').'?period='.now()->format('Y-m').'&kind=amortization')->assertOk();

        $this->post(route('v2.acc.asset.save'), $this->input(['asset_name' => 'ZZTEST via route']))->assertRedirect();
        $a = FinAsset::where('asset_name', 'ZZTEST via route')->firstOrFail();
        $this->get(route('v2.screen', 'acc-fixed-asset-form').'?id='.$a->asset_id)->assertOk()->assertSee($a->asset_code);
        $this->post(route('v2.acc.asset.save'), $this->input(['asset_name' => 'ZZTEST bad', 'cost' => 0]))->assertSessionHasErrors('cost');
        $this->assertSame(0, FinAsset::where('asset_name', 'ZZTEST bad')->count());

        $active = FinAsset::where('status', 'ACTIVE')->first();
        if ($active) {
            $this->get(route('v2.screen', 'acc-fixed-asset-form').'?id='.$active->asset_id)->assertOk()->assertSee($active->asset_code)->assertSee('Dispose');
        }
    }

    public function test_grid_menu_and_v1_state(): void
    {
        $grid = Grids::find('acc-fixed-asset');
        $this->assertStringContainsString('/v2/s/acc-fixed-asset-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/acc-fixed-asset-form?new=1', $grid->newUrl());

        $this->assertFileDoesNotExist(resource_path('views/pages/finance/assets.blade.php'));
        $this->assertArrayNotHasKey('ACC_FIXEDASSET', MenuRoutes::map());
        $this->actingAs($this->admin())->get('/finance/assets')->assertRedirect('/v2/s/acc-fixed-asset');
        $this->assertSame('v2:acc-depreciation-run', DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'ACC_DEPR_RUN')->value('target'));
    }
}
