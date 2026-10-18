<?php

/*
 * REMARK
 * Tujuan       : Menjaga jadwal amortisasi v2: aturan nilai dan kategori, buat jadwal + catat jurnal dalam satu transaksi (diuji di dalam transaksi yang dibatalkan, tidak ada yang tersisa di GL), jadwal tidak tertinggal bila pencatatan gagal, pemetaan status, form / grid terbuka, v1 sudah pindah ke arsip.
 * Sumber hulu  : App\Services\Acc\AmortizationScheduleService, AssetService, layar acc-amortization-form, routes/v2_finance.php.
 * Input        : User johiwan (admin); data uji hanya hidup di dalam transaksi tm2acc yang di-rollback; aset amortisasi yang sudah ada hanya dibaca.
 * Output       : Lulus bila aturan terpenuhi dan tidak ada jalur ke halaman v1 finance.amortization.
 * Dipakai oleh : php artisan test --filter=AmortizationScheduleServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAsset;
use App\Models\Acc\FinAssetCategory;
use App\Models\Acc\FinVoucher;
use App\Models\Sys\SecUser;
use App\Services\Acc\AmortizationScheduleService;
use App\Services\Acc\AssetService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AmortizationScheduleServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        while (DB::connection('tm2acc')->transactionLevel() > 0) {
            DB::connection('tm2acc')->rollBack();
        }
        $this->assertSame(0, FinAsset::withoutGlobalScopes()->where('asset_name', 'like', 'ZZTEST%')->count(), 'jadwal uji harus bersih');
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function category(): FinAssetCategory
    {
        return FinAssetCategory::whereIn('asset_type', AmortizationScheduleService::TYPES)->where('activate', true)->orderBy('category_id')->firstOrFail();
    }

    private function input(array $over = []): array
    {
        return $over + ['asset_name' => 'ZZTEST insurance', 'category_id' => $this->category()->category_id, 'acq_date' => now()->toDateString(), 'start_date' => now()->startOfMonth()->toDateString(),
            'cost' => 12000000, 'life' => 12, 'credit_account_id' => (int) FinAccount::where('account_code', '111200')->value('account_id'), 'source_ref' => 'ZZTEST policy'];
    }

    public function test_defaults_and_rules(): void
    {
        $svc = app(AmortizationScheduleService::class);
        $def = $svc->defaults($this->category());
        $this->assertSame((int) ($this->category()->useful_life_months ?: 12), $def['life']);
        $this->assertNotEmpty($svc->categoryOptions());

        foreach ([$this->input(['cost' => 0]), $this->input(['life' => 0]), $this->input(['asset_name' => '']), $this->input(['credit_account_id' => 0])] as $bad) {
            try {
                $svc->create($bad, $this->admin());
                $this->fail('input must be refused');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $fixed = FinAssetCategory::where('asset_type', 'FIXED')->firstOrFail();
        $this->expectException(HttpException::class);
        $svc->create($this->input(['category_id' => $fixed->category_id]), $this->admin());
    }

    public function test_create_records_the_journal_in_one_transaction(): void
    {
        $svc = app(AmortizationScheduleService::class);
        DB::connection('tm2acc')->beginTransaction();
        try {
            $a = $svc->create($this->input(), $this->admin());
        } catch (HttpException $e) {
            DB::connection('tm2acc')->rollBack();
            $this->markTestSkipped('The period is not open for posting: '.$e->getMessage());
        }

        $this->assertMatchesRegularExpression('/^AM-'.$this->category()->category_code.'-\d{4}-\d{4}$/', $a->asset_code);
        $this->assertSame('ACTIVE', $a->status);
        $this->assertSame('SL', $a->method);
        $this->assertSame(0.0, (float) $a->residual_value);
        $v = FinVoucher::withoutGlobalScopes()->with('lines')->findOrFail($a->capitalize_voucher_id);
        $this->assertSame('POSTED', $v->status);
        $this->assertSame(12000000.0, (float) $v->lines->sum('debit'));
        $this->assertSame(1000000.0, $svc->monthly($a));
        $this->assertSame(['confirmed', 'Active'], $svc->formState($a));
        $this->assertCount(12, $svc->schedule($a));
        DB::connection('tm2acc')->rollBack();

        $this->assertSame(0, FinAsset::withoutGlobalScopes()->where('asset_name', 'ZZTEST insurance')->count());
    }

    public function test_failed_recording_leaves_no_half_made_schedule(): void
    {
        $mock = Mockery::mock(AssetService::class)->makePartial();
        $mock->shouldReceive('capitalize')->andThrow(new HttpException(422, 'The period is closed.'));
        $this->app->instance(AssetService::class, $mock);

        try {
            app(AmortizationScheduleService::class)->create($this->input(), $this->admin());
            $this->fail('capitalize must fail');
        } catch (HttpException $e) {
            $this->assertSame('The period is closed.', $e->getMessage());
        }
        $this->assertSame(0, FinAsset::withoutGlobalScopes()->where('asset_name', 'ZZTEST insurance')->count());
    }

    public function test_form_state_mapping(): void
    {
        $svc = app(AmortizationScheduleService::class);
        $this->assertSame(['confirmed', 'Active'], $svc->formState(new FinAsset(['status' => 'ACTIVE'])));
        $this->assertSame(['closed', 'Fully amortized'], $svc->formState(new FinAsset(['status' => 'FULLY_DEPRECIATED'])));
        $this->assertSame(['draft', null], $svc->formState(new FinAsset(['status' => 'DRAFT'])));
    }

    public function test_forms_and_save_route_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        $c = $this->category();
        $this->get(route('v2.screen', 'acc-amortization'))->assertOk();
        $this->get(route('v2.screen', 'acc-amortization-form').'?new=1')->assertOk()->assertSee('Start an amortization schedule');
        $this->get(route('v2.screen', 'acc-amortization-form').'?category='.$c->category_id)->assertOk()->assertSee('New Amortization Schedule');

        $this->post(route('v2.acc.amort.save'), $this->input(['asset_name' => 'ZZTEST bad', 'cost' => 0]))->assertSessionHasErrors('cost');
        $this->assertSame(0, FinAsset::where('asset_name', 'ZZTEST bad')->count());

        DB::connection('tm2acc')->beginTransaction();
        $this->post(route('v2.acc.amort.save'), $this->input(['asset_name' => 'ZZTEST via route']))->assertRedirect();
        $a = FinAsset::where('asset_name', 'ZZTEST via route')->first();
        if ($a) {
            $this->get(route('v2.screen', 'acc-amortization-form').'?id='.$a->asset_id)->assertOk()->assertSee($a->asset_code)->assertSee('Schedule');
        }
        DB::connection('tm2acc')->rollBack();

        $existing = FinAsset::whereIn('category_id', FinAssetCategory::whereIn('asset_type', AmortizationScheduleService::TYPES)->select('category_id'))->first();
        if ($existing) {
            $this->get(route('v2.screen', 'acc-amortization-form').'?id='.$existing->asset_id)->assertOk()->assertSee($existing->asset_code);
        }
    }

    public function test_grid_and_v1_state(): void
    {
        $grid = Grids::find('acc-amortization');
        $this->assertStringContainsString('/v2/s/acc-amortization-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/acc-amortization-form?new=1', $grid->newUrl());

        $this->assertFileDoesNotExist(resource_path('views/pages/finance/amortization.blade.php'));
        $this->assertArrayNotHasKey('ACC_AMORT', MenuRoutes::map());
        $this->actingAs($this->admin())->get('/finance/amortization')->assertRedirect('/v2/s/acc-amortization');
    }
}
