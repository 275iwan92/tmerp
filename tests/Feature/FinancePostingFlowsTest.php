<?php

/*
 * REMARK
 * Tujuan       : Membuktikan jalur POSTING layar Finance v2 (T3a-T3f) benar-benar jalan sampai GL: jurnal manual (submit, approve, post, reverse), voucher kas & bank (post), tagihan vendor (confirm = post, pay), aset tetap (capitalize, depreciation run, dispose).
 * Sumber hulu  : App\Services\Acc\{ManualJournalService, CashBankVoucherService, VendorBillService, FixedAssetService} dan service posting di bawahnya.
 * Input        : User johiwan (admin); SEMUA data uji hidup di dalam satu transaksi tm2acc yang di-rollback di tearDown, jadi tidak ada yang tersisa di GL.
 * Output       : Lulus bila tiap jalur menghasilkan status dan voucher yang benar.
 * Dipakai oleh : php artisan test --filter=FinancePostingFlowsTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAsset;
use App\Models\Acc\FinAssetCategory;
use App\Models\Acc\FinAssetDepr;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinVoucher;
use App\Models\Master\SupSupplier;
use App\Models\Sys\SecUser;
use App\Services\Acc\CashBankVoucherService;
use App\Services\Acc\FixedAssetService;
use App\Services\Acc\ManualJournalService;
use App\Services\Acc\VendorBillService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancePostingFlowsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2acc')->beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::connection('tm2acc')->transactionLevel() > 0) {
            DB::connection('tm2acc')->rollBack();
        }
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function accounts(int $n = 2): array
    {
        return FinAccount::where('is_postable', true)->where('activate', true)->where('is_recon', false)->where('account_code', 'not like', '111%')->orderBy('account_code')->limit($n)->pluck('account_id')->all();
    }

    private function bank(): int
    {
        return (int) FinAccount::where('account_code', '111200')->value('account_id');
    }

    public function test_manual_journal_runs_submit_approve_post_and_reverse(): void
    {
        $svc = app(ManualJournalService::class);
        $user = $this->admin();
        [$a, $b] = $this->accounts();
        $v = $svc->save(null, ['entry_date' => now()->toDateString(), 'description' => 'ZZTEST flow', 'journal_kind' => 'MANUAL'],
            [['account_id' => $a, 'debit' => 5000, 'credit' => ''], ['account_id' => $b, 'debit' => '', 'credit' => 5000]], $user);

        $svc->submit($v, $user);
        $v->refresh();
        $this->assertContains($v->status, ['SUBMITTED', 'APPROVED']);
        if ($v->status === 'SUBMITTED') {
            $svc->approve($v, $user);
            $v->refresh();
        }
        $this->assertSame('APPROVED', $v->status);

        $svc->post($v, $user);
        $v->refresh();
        $this->assertSame('POSTED', $v->status);
        $this->assertTrue($svc->canReverse($v));

        $rev = $svc->reverse($v, now()->toDateString(), 'test reversal', $user);
        $this->assertSame('POSTED', $rev->status);
        $this->assertSame('REVERSAL', $rev->journal_kind);
        $this->assertSame($rev->voucher_id, $v->fresh()->reversed_by_id);
        $this->assertSame(['closed', 'Reversed'], $svc->formState($v->fresh()));
    }

    public function test_cash_bank_voucher_posts_when_balanced(): void
    {
        $svc = app(CashBankVoucherService::class);
        $user = $this->admin();
        [$other] = $this->accounts(1);
        $v = $svc->save(null, ['voucher_type' => 'BBK', 'entry_date' => now()->toDateString(), 'main_account_id' => $this->bank(), 'partner_name' => 'ZZTEST flow'],
            [['account_id' => $this->bank(), 'desc' => 'Bank', 'debit' => '', 'credit' => 7000], ['account_id' => $other, 'desc' => 'Other', 'debit' => 7000, 'credit' => '']], $user);

        $svc->post($v, $user);
        $v->refresh();
        $this->assertSame('POSTED', $v->status);
        $this->assertNotNull($v->posted_at);
        $this->assertSame(7000.0, $v->totalAmount());
        $this->assertSame(['confirmed', 'Posted'], $svc->formState($v));
    }

    public function test_vendor_bill_posts_and_is_paid_in_full(): void
    {
        $svc = app(VendorBillService::class);
        $user = $this->admin();
        $supplier = SupSupplier::where('activate', true)->orderBy('id_supplier')->firstOrFail();
        $bill = $svc->create(['supplier_id' => $supplier->id_supplier, 'partner_ref' => 'ZZTEST-FLOW', 'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'description' => 'ZZTEST flow', 'expense_account_id' => (int) FinAccount::where('account_code', '211900')->value('account_id'), 'subtotal' => 2000000, 'ppn' => true, 'pph_code' => 'PPH23'], $user);

        $this->assertSame('posted', $svc->confirm($bill, $user));
        $bill->refresh();
        $this->assertSame('POSTED', $bill->doc_status);
        $voucher = FinVoucher::withoutGlobalScopes()->with('lines')->findOrFail($bill->voucher_id);
        $this->assertEqualsWithDelta((float) $voucher->lines->sum('debit'), (float) $voucher->lines->sum('credit'), 0.01);
        $this->assertSame(['confirmed', 'Open'], $svc->formState($bill));

        $outstanding = $bill->outstanding();
        $pay = $svc->pay($bill, ['pay_date' => now()->toDateString(), 'pay_bank' => $this->bank(), 'pay_note' => 'ZZTEST'], [$bill->invoice_id => ['pay' => $outstanding]], $user);
        $this->assertSame('POSTED', $pay->status);
        $bill->refresh();
        $this->assertSame('PAID', $bill->payment_status);
        $this->assertSame($outstanding, (float) $bill->paid_amount);
        $this->assertSame(['closed', 'Paid'], $svc->formState($bill));
        $this->assertSame(1, FinInvoice::withoutGlobalScopes()->where('invoice_id', $bill->invoice_id)->count());
    }

    public function test_fixed_asset_capitalizes_depreciates_and_disposes(): void
    {
        $svc = app(FixedAssetService::class);
        $user = $this->admin();
        $cat = FinAssetCategory::where('asset_type', 'FIXED')->where('activate', true)->orderBy('category_id')->firstOrFail();
        $a = $svc->save(null, ['asset_name' => 'ZZTEST flow asset', 'category_id' => $cat->category_id, 'acq_date' => now()->startOfMonth()->toDateString(), 'start_date' => now()->startOfMonth()->toDateString(),
            'cost' => 48000000, 'residual' => 0, 'life' => 48, 'method' => 'SL', 'credit_account_id' => (int) FinAccount::where('account_code', '211900')->value('account_id')], $user);

        $svc->capitalize($a->refresh(), $user);
        $a->refresh();
        $this->assertSame('ACTIVE', $a->status);
        $this->assertNotNull($a->capitalize_voucher_id);

        $voucherId = $svc->run(now()->format('Y-m'), 'fixed', $user);
        $this->assertNotNull($voucherId);
        $row = FinAssetDepr::withoutGlobalScopes()->where('asset_id', $a->asset_id)->first();
        $this->assertSame(1000000.0, (float) $row->amount);
        $this->assertNull(collect($svc->runPreview(now()->format('Y-m'), 'fixed'))->first(fn ($p) => $p->asset->asset_id === $a->asset_id), 'the same period cannot be depreciated twice');

        $svc->dispose($a, ['disp_date' => now()->toDateString(), 'disp_amount' => 0, 'disp_bank' => $this->bank()], $user);
        $a->refresh();
        $this->assertSame('DISPOSED', $a->status);
        $this->assertSame(['closed', 'Disposed'], $svc->formState($a));
        $this->assertSame(1, FinAsset::withoutGlobalScopes()->where('asset_id', $a->asset_id)->count());
    }
}
