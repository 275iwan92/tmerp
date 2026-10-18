<?php

/*
 * REMARK
 * Tujuan       : Menjaga Journal Entry v2: aturan simpan (debit = kredit, tidak nol, akun kontrol AR/AP ditolak, satu baris tidak debit dan kredit), lifecycle Draft > Submitted/Approved > Return > Delete, pemetaan status panel, form dan grid terbuka, v1 sudah pindah ke arsip.
 * Sumber hulu  : App\Services\Acc\ManualJournalService, layar acc-journal-form, grid acc-journal, routes/v2_finance.php.
 * Input        : User johiwan (admin); jurnal uji berdeskripsi ZZTEST* di tm2acc.fin_voucher (+ fin_voucher_line), dihapus di tearDown. Tes tidak memposting ke GL.
 * Output       : Lulus bila aturan terpenuhi dan tidak ada jalur ke halaman v1 finance.journals.
 * Dipakai oleh : php artisan test --filter=ManualJournalServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Sys\SecUser;
use App\Services\Acc\ManualJournalService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManualJournalServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        $ids = FinVoucher::query()->where('description', 'like', 'ZZTEST%')->pluck('voucher_id');
        FinVoucherLine::query()->whereIn('voucher_id', $ids)->delete();
        FinVoucher::query()->whereIn('voucher_id', $ids)->delete();
        $this->assertSame(0, FinVoucher::query()->where('description', 'like', 'ZZTEST%')->count(), 'voucher uji harus bersih');
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function accounts(): array
    {
        return FinAccount::query()->where('is_postable', true)->where('activate', true)->where('is_recon', false)->orderBy('account_code')->limit(2)->pluck('account_id')->all();
    }

    private function header(array $over = []): array
    {
        return $over + ['entry_date' => now()->toDateString(), 'description' => 'ZZTEST journal', 'journal_kind' => 'MANUAL'];
    }

    private function lines(int $debit = 1000, int $credit = 1000): array
    {
        [$a, $b] = $this->accounts();

        return [['account_id' => $a, 'debit' => $debit, 'credit' => '', 'narration' => 'x'], ['account_id' => $b, 'debit' => '', 'credit' => $credit]];
    }

    public function test_draft_is_saved_with_number_and_lines_then_updated(): void
    {
        $svc = app(ManualJournalService::class);
        $v = $svc->save(null, $this->header(), $this->lines(), $this->admin());

        $this->assertSame('DRAFT', $v->status);
        $this->assertMatchesRegularExpression('/^JV-\d{4}-\d{2}-\d{4}$/', $v->voucher_number);
        $this->assertCount(2, $v->lines);
        $this->assertSame(1000.0, $v->totalAmount());

        $v2 = $svc->save($v, $this->header(['description' => 'ZZTEST renamed']), $this->lines(2500, 2500), $this->admin());
        $this->assertSame($v->voucher_number, $v2->voucher_number);
        $this->assertSame(2500.0, $v2->totalAmount());
        $this->assertSame('draft', $svc->formState($v2)[0]);
    }

    public function test_unbalanced_zero_recon_and_mixed_lines_are_refused(): void
    {
        $svc = app(ManualJournalService::class);
        $user = $this->admin();
        foreach ([[$this->lines(1000, 900)], [$this->lines(0, 0)]] as [$lines]) {
            try {
                $svc->save(null, $this->header(), $lines, $user);
                $this->fail('journal must be refused');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('lines', $e->errors());
            }
        }

        [$a, $b] = $this->accounts();
        try {
            $svc->save(null, $this->header(), [['account_id' => $a, 'debit' => 5, 'credit' => 5], ['account_id' => $b, 'debit' => '', 'credit' => '']], $user);
            $this->fail('a line with debit and credit must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }

        $recon = FinAccount::query()->where('is_recon', true)->where('is_postable', true)->value('account_id');
        if ($recon) {
            try {
                $svc->save(null, $this->header(), [['account_id' => $recon, 'debit' => 10, 'credit' => ''], ['account_id' => $b, 'debit' => '', 'credit' => 10]], $user);
                $this->fail('control account must be refused');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Control account', $e->errors()['lines'][0]);
            }
        }
        $this->assertSame(0, FinVoucher::query()->where('description', 'like', 'ZZTEST%')->count());
    }

    public function test_submit_return_and_delete_follow_the_lifecycle(): void
    {
        $svc = app(ManualJournalService::class);
        $user = $this->admin();
        $v = $svc->save(null, $this->header(), $this->lines(), $user);

        $svc->submit($v, $user);
        $v->refresh();
        $this->assertContains($v->status, ['SUBMITTED', 'APPROVED']);
        $this->assertContains($svc->formState($v)[0], ['approval', 'approved']);

        $svc->returnToDraft($v, $user);
        $this->assertSame('DRAFT', $v->fresh()->status);

        $svc->delete($v->fresh());
        $this->assertNull(FinVoucher::query()->find($v->voucher_id));
        $this->assertSame(0, FinVoucherLine::query()->where('voucher_id', $v->voucher_id)->count());
    }

    public function test_form_state_mapping_and_reverse_rules(): void
    {
        $svc = app(ManualJournalService::class);
        $this->assertSame(['confirmed', 'Posted'], $svc->formState(new FinVoucher(['status' => 'POSTED'])));
        $this->assertSame(['closed', 'Reversed'], $svc->formState(new FinVoucher(['status' => 'POSTED', 'reversed_by_id' => 9])));
        $this->assertSame(['approval', 'Pending approval'], $svc->formState(new FinVoucher(['status' => 'SUBMITTED'])));
        $this->assertTrue($svc->canReverse(new FinVoucher(['status' => 'POSTED', 'journal_kind' => 'MANUAL'])));
        $this->assertFalse($svc->canReverse(new FinVoucher(['status' => 'POSTED', 'journal_kind' => 'REVERSAL'])));
        $this->assertFalse($svc->canReverse(new FinVoucher(['status' => 'DRAFT'])));

        $draft = $svc->save(null, $this->header(), $this->lines(), $this->admin());
        $this->expectException(HttpException::class);
        $svc->reverse($draft, now()->toDateString(), 'test', $this->admin());
    }

    public function test_form_grid_and_save_route_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('v2.screen', 'acc-journal'))->assertOk();
        $this->get(route('v2.screen', 'acc-journal-form').'?new=1')->assertOk()->assertSee('New Journal Entry');

        $this->post(route('v2.acc.journal.save'), $this->header(['description' => 'ZZTEST via route']) + ['lines' => $this->lines()])->assertRedirect();
        $v = FinVoucher::query()->where('description', 'ZZTEST via route')->firstOrFail();
        $this->get(route('v2.screen', 'acc-journal-form').'?id='.$v->voucher_id)->assertOk()->assertSee($v->voucher_number);

        $this->post(route('v2.acc.journal.save'), $this->header(['description' => 'ZZTEST unbalanced']) + ['lines' => $this->lines(10, 5)])->assertSessionHasErrors('lines');
        $this->assertSame(0, FinVoucher::query()->where('description', 'ZZTEST unbalanced')->count());

        $this->post(route('v2.acc.journal.save'), ['id' => $v->voucher_id, 'action' => 'delete'])->assertRedirect(route('v2.screen', 'acc-journal'));
        $this->assertNull(FinVoucher::query()->find($v->voucher_id));
    }

    public function test_grid_opens_v2_form_and_v1_is_gone(): void
    {
        $grid = Grids::find('acc-journal');
        $this->assertStringContainsString('/v2/s/acc-journal-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/acc-journal-form?new=1', $grid->newUrl());

        $this->assertFileDoesNotExist(resource_path('views/pages/finance/journals.blade.php'));
        $this->assertArrayNotHasKey('ACC_JRN', MenuRoutes::map());
        $this->actingAs($this->admin())->get('/finance/journals')->assertRedirect('/v2/s/acc-journal');
    }
}
