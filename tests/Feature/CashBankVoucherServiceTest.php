<?php

/*
 * REMARK
 * Tujuan       : Menjaga voucher Kas & Bank v2: simpan draft (nomor, baris, pajak), baris kosong dibuang, starter line menurut jenis, Cancel Draft, Duplicate, Confirm ditolak bila tidak seimbang, form dan grid terbuka, v1 sudah pindah ke arsip.
 * Sumber hulu  : App\Services\Acc\CashBankVoucherService, layar acc-cash-bank-form, grid acc-cash-bank, routes/v2_finance.php.
 * Input        : User johiwan (admin); voucher uji bermitra ZZTEST* di tm2acc.fin_voucher (+ fin_voucher_line), dihapus di tearDown. Tes tidak memposting ke GL.
 * Output       : Lulus bila aturan terpenuhi dan tidak ada jalur ke halaman v1 finance.cash-bank.
 * Dipakai oleh : php artisan test --filter=CashBankVoucherServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Sys\SecUser;
use App\Services\Acc\CashBankVoucherService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CashBankVoucherServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        $ids = FinVoucher::query()->where('partner_name', 'like', 'ZZTEST%')->pluck('voucher_id');
        FinVoucherLine::query()->whereIn('voucher_id', $ids)->delete();
        FinVoucher::query()->whereIn('voucher_id', $ids)->delete();
        $this->assertSame(0, FinVoucher::query()->where('partner_name', 'like', 'ZZTEST%')->count(), 'voucher uji harus bersih');
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function bank(): int
    {
        return (int) FinAccount::query()->where('account_code', 'like', '111%')->where('is_postable', true)->orderBy('account_code')->value('account_id');
    }

    private function other(): int
    {
        return (int) FinAccount::query()->where('is_postable', true)->where('account_code', 'not like', '111%')->orderBy('account_code')->value('account_id');
    }

    private function header(array $over = []): array
    {
        return $over + ['voucher_type' => 'BBK', 'entry_date' => now()->toDateString(), 'main_account_id' => $this->bank(), 'partner_name' => 'ZZTEST partner'];
    }

    private function lines(int $debit = 800, int $credit = 800): array
    {
        return [['account_id' => $this->bank(), 'desc' => 'Bank', 'debit' => '', 'credit' => $credit], ['account_id' => $this->other(), 'desc' => 'Other', 'debit' => $debit, 'credit' => ''],
            ['account_id' => '', 'desc' => '', 'debit' => '', 'credit' => '']];
    }

    public function test_draft_is_saved_with_number_tax_fields_and_blank_lines_dropped(): void
    {
        $svc = app(CashBankVoucherService::class);
        $v = $svc->save(null, $this->header(['ppn_type' => 'MASUKAN_11', 'efaktur_no' => '010.003-26.0000001', 'pph_type' => 'PPH23_2', 'bupot_no' => 'BP-1']), $this->lines(), $this->admin());

        $this->assertSame('DRAFT', $v->status);
        $this->assertMatchesRegularExpression('#^BBK/\d{4}/\d{2}/\d{3}$#', $v->voucher_number);
        $this->assertCount(2, $v->lines);
        $this->assertSame('MASUKAN_11', $v->ppn_type);
        $this->assertSame('PPH23_2', $v->pph_type);
        $this->assertSame('Bank Payment Voucher — ZZTEST partner', $v->description);

        $v2 = $svc->save($v, $this->header(['partner_name' => 'ZZTEST renamed']), $this->lines(900, 900), $this->admin());
        $this->assertSame($v->voucher_number, $v2->voucher_number);
        $this->assertSame(900.0, $v2->totalAmount());
        $this->assertSame(['draft', null], $svc->formState($v2));
    }

    public function test_header_and_line_rules_are_refused(): void
    {
        $svc = app(CashBankVoucherService::class);
        foreach ([$this->header(['main_account_id' => null]), $this->header(['voucher_type' => 'XXX'])] as $h) {
            try {
                $svc->save(null, $h, $this->lines(), $this->admin());
                $this->fail('header must be refused');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        try {
            $svc->save(null, $this->header(), [['account_id' => '', 'debit' => '', 'credit' => '']], $this->admin());
            $this->fail('a voucher without a valid line must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }
        $this->assertSame(0, FinVoucher::query()->where('partner_name', 'like', 'ZZTEST%')->count());
    }

    public function test_starter_line_follows_the_voucher_type(): void
    {
        $svc = app(CashBankVoucherService::class);
        $in = $svc->starterLines('BBM', 7)[0];
        $out = $svc->starterLines('BKK', 7)[0];
        $this->assertSame(['', '0'], [$in['debit'], $in['credit']]);
        $this->assertSame(['0', ''], [$out['debit'], $out['credit']]);
        $this->assertSame('Bank Payment (BBK)', $svc->typeOptions()['BBK']);
        $this->assertTrue($svc->isInflow('BKM'));
        $this->assertFalse($svc->isInflow('BBK'));
    }

    public function test_cancel_duplicate_and_post_guards(): void
    {
        $svc = app(CashBankVoucherService::class);
        $user = $this->admin();
        $v = $svc->save(null, $this->header(), $this->lines(), $user);

        $svc->cancel($v, $user);
        $v->refresh();
        $this->assertSame('CANCELLED', $v->status);
        $this->assertTrue($v->stat_cancel);
        $this->assertSame(['cancelled', null], $svc->formState($v));

        $copy = $svc->duplicate($v, $user);
        $this->assertSame('DRAFT', $copy->status);
        $this->assertNotSame($v->voucher_number, $copy->voucher_number);
        $this->assertCount(2, $copy->lines);

        $this->expectException(HttpException::class);
        $svc->post($svc->save($copy, $this->header(), $this->lines(800, 500), $user), $user);
    }

    public function test_form_grid_and_save_route_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('v2.screen', 'acc-cash-bank'))->assertOk();
        $this->get(route('v2.screen', 'acc-cash-bank-form').'?new=1')->assertOk()->assertSee('Start a voucher');
        $this->get(route('v2.screen', 'acc-cash-bank-form').'?type=BBK&account='.$this->bank())->assertOk()->assertSee('New Cash and Bank Voucher');

        $this->post(route('v2.acc.cashbank.save'), $this->header(['partner_name' => 'ZZTEST via route']) + ['lines' => $this->lines()])->assertRedirect();
        $v = FinVoucher::query()->where('partner_name', 'ZZTEST via route')->firstOrFail();
        $this->get(route('v2.screen', 'acc-cash-bank-form').'?id='.$v->voucher_id)->assertOk()->assertSee($v->voucher_number);

        $this->post(route('v2.acc.cashbank.save'), $this->header(['partner_name' => 'ZZTEST via route', 'id' => $v->voucher_id, 'action' => 'confirm']) + ['lines' => $this->lines(800, 500)])
            ->assertSessionHasErrors('voucher');
        $this->assertSame('DRAFT', $v->fresh()->status);

        $this->post(route('v2.acc.cashbank.save'), ['id' => $v->voucher_id, 'action' => 'cancel'])->assertRedirect(route('v2.screen', 'acc-cash-bank'));
        $this->assertSame('CANCELLED', $v->fresh()->status);
    }

    public function test_grid_opens_v2_form_and_v1_is_gone(): void
    {
        $grid = Grids::find('acc-cash-bank');
        $this->assertStringContainsString('/v2/s/acc-cash-bank-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/acc-cash-bank-form?new=1', $grid->newUrl());

        $this->assertFileDoesNotExist(resource_path('views/pages/finance/cash-bank.blade.php'));
        $this->assertArrayNotHasKey('FIN_CASH', MenuRoutes::map());
        $this->actingAs($this->admin())->get('/finance/cash-bank')->assertRedirect('/v2/s/acc-cash-bank');
    }
}
