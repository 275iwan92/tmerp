<?php

/*
 * REMARK
 * Tujuan       : Menjaga Master of Accounts v2: aturan simpan akun (kode unik, header tidak diedit, bukan parent diri sendiri, account_id = MAX + 1), form dan grid terbuka, v1 sudah pindah ke arsip.
 * Sumber hulu  : App\Services\Acc\ChartOfAccountService, layar acc-coa-form, grid acc-coa, routes/v2_finance.php.
 * Input        : User johiwan (admin); baris uji kode ZZTEST* di tm2acc.fin_account (dihapus di tearDown).
 * Output       : Lulus bila aturan terpenuhi dan tidak ada jalur ke halaman v1 finance.coa.
 * Dipakai oleh : php artisan test --filter=ChartOfAccountServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Acc\FinAccount;
use App\Models\Sys\SecUser;
use App\Services\Acc\ChartOfAccountService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChartOfAccountServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        FinAccount::query()->where('account_code', 'like', 'ZZTEST%')->delete();
        $this->assertSame(0, FinAccount::query()->where('account_code', 'like', 'ZZTEST%')->count(), 'baris uji harus bersih');
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function input(array $over = []): array
    {
        return $over + ['account_code' => 'ZZTEST1', 'account_name' => 'Test account', 'account_type' => 'ASSET', 'normal_balance' => 'DEBIT', 'is_postable' => 1, 'activate' => 1];
    }

    public function test_new_account_gets_next_id_and_can_be_edited_and_deactivated(): void
    {
        $svc = app(ChartOfAccountService::class);
        $max = (int) FinAccount::query()->max('account_id');

        $a = $svc->save(null, $this->input(), $this->admin());
        $this->assertSame($max + 1, $a->account_id);
        $this->assertTrue($a->activate);
        $this->assertTrue($a->is_postable);

        $a = $svc->save($a, $this->input(['account_name' => 'Renamed', 'is_recon' => 1]), $this->admin());
        $this->assertSame('Renamed', $a->fresh()->account_name);
        $this->assertTrue($a->fresh()->is_recon);

        $svc->setActive($a, false, $this->admin());
        $this->assertFalse($a->fresh()->activate);
    }

    public function test_duplicate_code_and_self_parent_and_header_edit_are_refused(): void
    {
        $svc = app(ChartOfAccountService::class);
        $a = $svc->save(null, $this->input(), $this->admin());

        try {
            $svc->save(null, $this->input(['account_name' => 'Other']), $this->admin());
            $this->fail('duplicate code must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('account_code', $e->errors());
        }

        try {
            $svc->save($a, $this->input(['parent_id' => $a->account_id]), $this->admin());
            $this->fail('self parent must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_id', $e->errors());
        }

        $header = $svc->save(null, $this->input(['account_code' => 'ZZTEST2', 'is_postable' => 0]), $this->admin());
        $this->expectException(ValidationException::class);
        $svc->save($header, $this->input(['account_code' => 'ZZTEST2', 'account_name' => 'Edited header', 'is_postable' => 0]), $this->admin());
    }

    public function test_form_grid_and_save_route_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('v2.screen', 'acc-coa').'')->assertOk();
        $this->get(route('v2.screen', 'acc-coa-form').'?new=1')->assertOk()->assertSee('New Account');

        $this->post(route('v2.acc.coa.save'), $this->input(['account_code' => 'ZZTEST3']))->assertRedirect();
        $a = FinAccount::query()->where('account_code', 'ZZTEST3')->firstOrFail();
        $this->get(route('v2.screen', 'acc-coa-form').'?id='.$a->account_id)->assertOk()->assertSee('Test account');

        $this->post(route('v2.acc.coa.save'), ['id' => $a->account_id, 'action' => 'deactivate'])->assertRedirect();
        $this->assertFalse($a->fresh()->activate);
    }

    public function test_grid_opens_v2_form_and_v1_is_gone(): void
    {
        $grid = Grids::find('acc-coa');
        $this->assertStringContainsString('/v2/s/acc-coa-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/acc-coa-form?new=1', $grid->newUrl());

        $this->assertFileDoesNotExist(resource_path('views/pages/finance/coa.blade.php'));
        $this->assertArrayNotHasKey('ACC_COA', MenuRoutes::map());
        $this->actingAs($this->admin())->get('/finance/coa')->assertRedirect('/v2/s/acc-coa');
    }
}
