<?php

namespace Tests\Feature;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAsset;
use App\Models\Acc\FinAssetCategory;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinJournal;
use App\Models\Acc\FinPaymentAlloc;
use App\Models\Acc\FinPeriod;
use App\Models\Acc\FinPeriodLog;
use App\Models\Acc\FinVoucher;
use App\Models\Master\CustCustomer;
use App\Models\Sys\SecUser;
use App\Services\Acc\ArApService;
use App\Services\Acc\AssetService;
use App\Services\Acc\FinPeriodGuard;
use App\Services\Acc\JournalPostingService;
use App\Services\Acc\LedgerService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// Regresi Accounting Process Engine (tm2acc). Test menulis ke MySQL sungguhan
// dan membersihkan sendiri di tearDown() -- tanpa RefreshDatabase, sesuai
// CLAUDE.md. Semua data uji ditandai created_by=acc_engine_test.
class AccountingEngineTest extends TestCase
{
    private const USER = 'acc_engine_test';

    private array $createdVoucherIds = [];

    private array $createdInvoiceIds = [];

    private array $createdAssetIds = [];

    private ?int $testPeriodId = null;

    protected function tearDown(): void
    {
        $lines = DB::connection('tm2acc')->table('fin_voucher')->where('created_by', self::USER)->pluck('voucher_id')
            ->merge($this->createdVoucherIds)->unique();
        DB::connection('tm2acc')->table('fin_payment_alloc')->whereIn('voucher_id', $lines)->orWhere('created_by', self::USER)->delete();
        DB::connection('tm2acc')->table('fin_asset_depr')->where('created_by', self::USER)->delete();
        DB::connection('tm2acc')->table('fin_asset')->where('created_by', self::USER)->delete();
        DB::connection('tm2acc')->table('fin_invoice')->where('created_by', self::USER)->delete();
        DB::connection('tm2acc')->table('fin_voucher_line')->whereIn('voucher_id', $lines)->delete();
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $lines)->update(['reversed_by_id' => null]);
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $lines)->delete();
        if ($this->testPeriodId) {
            DB::connection('tm2acc')->table('fin_period_log')->where('period_id', $this->testPeriodId)->where('changed_by', self::USER)->delete();
            DB::connection('tm2acc')->table('fin_period')->where('period_id', $this->testPeriodId)
                ->update(['status' => 'OPEN', 'closed_by' => null, 'closed_at' => null]);
        }

        parent::tearDown();
    }

    private function acct(string $code): int
    {
        return (int) FinAccount::withoutGlobalScopes()->where('account_code', $code)->value('account_id');
    }

    /** Periode uji di masa depan supaya tidak mengganggu data nyata/dummy. */
    private function openTestPeriod(): FinPeriod
    {
        $date = now()->addMonths(3)->startOfMonth();
        $period = FinPeriod::withoutGlobalScopes()->where('fiscal_year', $date->year)->where('period_no', $date->month)->first();
        if (! $period) {
            $this->markTestSkipped('Periode GL '.$date->format('Y-m').' belum digenerate.');
        }
        $this->testPeriodId = $period->period_id;
        $period->update(['status' => 'OPEN']);

        return $period;
    }

    private function draftJournal(string $date, float $amount = 1_500_000): FinVoucher
    {
        $svc = app(JournalPostingService::class);
        $v = $svc->saveDraft(null, [
            'voucher_number' => $svc->nextJournalNumber($date),
            'journal_id' => FinJournal::where('journal_code', 'GEN')->value('journal_id'),
            'journal_kind' => 'MANUAL', 'entry_date' => $date, 'posting_date' => $date,
            'description' => 'Test jurnal '.$date, 'source_type' => 'MANUAL', 'created_by' => self::USER,
        ], [
            ['account_id' => $this->acct('616000'), 'debit' => $amount],
            ['account_id' => $this->acct('213100'), 'credit' => $amount],
        ], self::USER);
        $v->update(['created_by' => self::USER]);
        $this->createdVoucherIds[] = $v->voucher_id;

        return $v->fresh();
    }

    public function test_posting_ditolak_saat_periode_tidak_open(): void
    {
        $period = $this->openTestPeriod();
        $date = $period->start_date->copy()->addDays(5)->toDateString();
        $v = $this->draftJournal($date);

        app(FinPeriodGuard::class)->changeStatus($period, 'ON_HOLD', 'Test periode on hold', self::USER);

        $this->expectExceptionMessageMatches('/ON_HOLD/');
        app(JournalPostingService::class)->postDraft($v, self::USER);
    }

    public function test_jurnal_tidak_balance_ditolak(): void
    {
        $period = $this->openTestPeriod();
        $svc = app(JournalPostingService::class);
        $date = $period->start_date->toDateString();
        $v = $svc->saveDraft(null, [
            'voucher_number' => $svc->nextJournalNumber($date),
            'journal_id' => FinJournal::where('journal_code', 'GEN')->value('journal_id'),
            'journal_kind' => 'MANUAL', 'entry_date' => $date, 'posting_date' => $date,
            'description' => 'Test tidak balance', 'source_type' => 'MANUAL', 'created_by' => self::USER,
        ], [
            ['account_id' => $this->acct('616000'), 'debit' => 900_000],
            ['account_id' => $this->acct('213100'), 'credit' => 500_000],
        ], self::USER);
        $v->update(['created_by' => self::USER]);
        $this->createdVoucherIds[] = $v->voucher_id;

        $this->expectExceptionMessageMatches('/balance/i');
        $svc->postDraft($v, self::USER);
    }

    public function test_jurnal_pembalik_membalik_tepat_dan_menandai_asal(): void
    {
        $period = $this->openTestPeriod();
        $date = $period->start_date->copy()->addDays(2)->toDateString();
        $svc = app(JournalPostingService::class);
        $v = $this->draftJournal($date, 2_750_000);
        $svc->postDraft($v, self::USER);

        $reversal = $svc->reverse($v->refresh(), $date, self::USER, 'test');
        $this->createdVoucherIds[] = $reversal->voucher_id;

        $this->assertSame('REVERSAL', $reversal->journal_kind);
        $this->assertEqualsWithDelta(2_750_000, $reversal->lines->sum('credit'), 0.01, 'debit asal harus jadi kredit pembalik');
        $this->assertSame($reversal->voucher_id, (int) $v->refresh()->reversed_by_id);
        // assertDatabaseHas() memakai koneksi default (sqlite di phpunit.xml) --
        // modul ini hidup di tm2acc, jadi cek langsung ke koneksinya.
        $this->assertTrue(DB::connection('tm2acc')->table('fin_voucher')
            ->where('voucher_id', $reversal->voucher_id)->where('reversal_of_id', $v->voucher_id)->exists());
    }

    public function test_voucher_posted_tidak_bisa_diedit_lagi(): void
    {
        $period = $this->openTestPeriod();
        $svc = app(JournalPostingService::class);
        $v = $this->draftJournal($period->start_date->toDateString());
        $svc->postDraft($v, self::USER);

        // Pesan lewat kamus (bahasa tampilan): yang diuji = kelas exception + bagian pesan yang tidak diterjemahkan.
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('DRAFT');
        $svc->saveDraft($v->refresh(), ['description' => 'diubah'], [
            ['account_id' => $this->acct('616000'), 'debit' => 1],
            ['account_id' => $this->acct('213100'), 'credit' => 1],
        ], self::USER);
    }

    public function test_faktur_penjualan_ppn_dan_jurnalnya(): void
    {
        $period = $this->openTestPeriod();
        $customer = CustCustomer::withoutGlobalScopes()->where('activate', true)->firstOrFail();
        $svc = app(ArApService::class);

        $invoice = $svc->createCustomerInvoice([
            'customer_id' => $customer->id_customer,
            'invoice_date' => $period->start_date->toDateString(),
            'due_date' => $period->start_date->copy()->addDays(30)->toDateString(),
            'subtotal' => 100_000_000, 'ppn' => true, 'description' => 'Test faktur',
            'revenue_account_id' => $this->acct('411000'),
        ], self::USER);
        $this->createdInvoiceIds[] = $invoice->invoice_id;
        $this->createdVoucherIds[] = $invoice->voucher_id;

        // PPN 12% x DPP 11/12 = 11% efektif (PMK 131/2024).
        $this->assertEqualsWithDelta(11_000_000, (float) $invoice->tax_amount, 1);
        $this->assertEqualsWithDelta(111_000_000, (float) $invoice->total_amount, 1);

        $lines = $invoice->voucher->lines;
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.01);
        $this->assertEqualsWithDelta(111_000_000, (float) $lines->firstWhere('account_id', $svc->arAccountFor($customer))->debit, 1);
        $this->assertSame('CUSTOMER', $lines->firstWhere('partner_type', 'CUSTOMER')?->partner_type, 'baris akun recon wajib punya partner');

        // Alokasi tidak boleh melebihi sisa.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage($invoice->invoice_number);
        $svc->settle('RECEIPT', $this->acct('111200'), $period->start_date->toDateString(),
            [$invoice->invoice_id => 200_000_000], self::USER);
    }

    public function test_penerimaan_parsial_lalu_lunas(): void
    {
        $period = $this->openTestPeriod();
        $customer = CustCustomer::withoutGlobalScopes()->where('activate', true)->firstOrFail();
        $svc = app(ArApService::class);
        $date = $period->start_date->toDateString();

        $invoice = $svc->createCustomerInvoice([
            'customer_id' => $customer->id_customer, 'invoice_date' => $date,
            'due_date' => $period->start_date->copy()->addDays(14)->toDateString(),
            'subtotal' => 50_000_000, 'ppn' => false, 'description' => 'Test faktur parsial',
            'revenue_account_id' => $this->acct('411000'),
        ], self::USER);
        $this->createdVoucherIds[] = $invoice->voucher_id;

        $v1 = $svc->settle('RECEIPT', $this->acct('111200'), $date, [$invoice->invoice_id => 20_000_000], self::USER);
        $this->createdVoucherIds[] = $v1->voucher_id;
        $this->assertSame('PARTIAL', $invoice->refresh()->payment_status);

        $v2 = $svc->settle('RECEIPT', $this->acct('111200'), $date, [$invoice->invoice_id => 30_000_000], self::USER);
        $this->createdVoucherIds[] = $v2->voucher_id;
        $this->assertSame('PAID', $invoice->refresh()->payment_status);
        $this->assertEqualsWithDelta(0, $invoice->outstanding(), 0.01);
        $this->assertSame(2, FinPaymentAlloc::withoutGlobalScopes()->where('invoice_id', $invoice->invoice_id)->count());
    }

    public function test_jadwal_penyusutan_garis_lurus_dan_saldo_menurun(): void
    {
        $svc = app(AssetService::class);
        $catSl = FinAssetCategory::withoutGlobalScopes()->where('category_code', 'ITEQ')->firstOrFail();

        $sl = new FinAsset([
            'asset_code' => 'TEST-SL', 'asset_name' => 'Test SL', 'category_id' => $catSl->category_id,
            'acquisition_date' => '2026-01-01', 'depr_start_date' => '2026-01-01',
            'acquisition_cost' => 48_000_000, 'residual_value' => 0, 'useful_life_months' => 48, 'method' => 'SL',
            'status' => 'ACTIVE',
        ]);
        $plan = $svc->plan($sl);
        $this->assertCount(48, $plan);
        $this->assertEqualsWithDelta(1_000_000, $plan[0], 0.01);
        $this->assertEqualsWithDelta(48_000_000, array_sum($plan), 0.01, 'total penyusutan = harga perolehan - residu');

        $ddb = new FinAsset([
            'asset_code' => 'TEST-DDB', 'asset_name' => 'Test DDB', 'category_id' => $catSl->category_id,
            'acquisition_date' => '2026-01-01', 'depr_start_date' => '2026-01-01',
            'acquisition_cost' => 100_000_000, 'residual_value' => 0, 'useful_life_months' => 48, 'method' => 'DDB',
            'status' => 'ACTIVE',
        ]);
        $planDdb = $svc->plan($ddb);
        $this->assertGreaterThan($planDdb[12], $planDdb[0], 'saldo menurun: beban tahun pertama lebih besar');
        $this->assertEqualsWithDelta(100_000_000, array_sum($planDdb), 0.01, 'saldo menurun tetap habis di akhir umur');
    }

    public function test_neraca_selalu_seimbang_dan_arus_kas_terekonsiliasi(): void
    {
        $ledger = app(LedgerService::class);
        $asOf = now()->toDateString();

        $tb = $ledger->trialBalance($asOf);
        $this->assertEqualsWithDelta($tb->sum('debit'), $tb->sum('credit'), 0.05, 'trial balance harus nol selisih');

        $bs = $ledger->balanceSheet($asOf);
        $this->assertEqualsWithDelta($bs['total_assets'], $bs['total_le'], 0.05, 'aset = kewajiban + ekuitas');

        $year = date('Y');
        $cf = $ledger->cashFlow("{$year}-01-01", $asOf);
        $this->assertEqualsWithDelta($cf['opening_cash'] + $cf['net_change'], $cf['closing_cash'], 0.05,
            'arus kas metode tidak langsung harus menjelaskan seluruh perubahan kas');
    }

    public function test_close_diblokir_saat_masih_ada_voucher_belum_posted(): void
    {
        $period = $this->openTestPeriod();
        $this->draftJournal($period->start_date->copy()->addDay()->toDateString());

        $blockers = app(FinPeriodGuard::class)->closeBlockers($period);
        $this->assertNotEmpty($blockers);
        $this->assertTrue(
            collect($blockers)->contains(fn ($b) => str_contains($b, 'belum POSTED')),
            'harus menolak close karena ada voucher draft: '.implode(' | ', $blockers)
        );
    }

    public function test_periode_closed_tidak_bisa_dibuka_lagi(): void
    {
        $period = $this->openTestPeriod();
        $guard = app(FinPeriodGuard::class);
        // Paksa CLOSED langsung (gate Inventory & checklist di luar cakupan test ini).
        $period->update(['status' => 'CLOSED']);
        FinPeriodLog::create(['period_id' => $period->period_id, 'from_status' => 'OPEN', 'to_status' => 'CLOSED',
            'reason' => 'Test closed', 'changed_by' => self::USER, 'changed_at' => now()]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('CLOSED');
        $guard->changeStatus($period->refresh(), 'OPEN', 'Coba buka lagi', self::USER);
    }

    public function test_faktur_duplikat_ditolak(): void
    {
        $period = $this->openTestPeriod();
        $customer = CustCustomer::withoutGlobalScopes()->where('activate', true)->firstOrFail();
        $svc = app(ArApService::class);
        $data = [
            'customer_id' => $customer->id_customer, 'invoice_date' => $period->start_date->toDateString(),
            'due_date' => $period->start_date->copy()->addDays(7)->toDateString(),
            'subtotal' => 12_345_000, 'ppn' => true, 'description' => 'Test duplikat',
            'revenue_account_id' => $this->acct('411000'),
        ];
        $first = $svc->createCustomerInvoice($data, self::USER);
        $this->createdVoucherIds[] = $first->voucher_id;

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage($first->invoice_number);
        $svc->createCustomerInvoice($data, self::USER);
    }

    public function test_semua_layar_finance_bisa_dirender(): void
    {
        $this->actingAs(SecUser::where('is_admin', true)->firstOrFail());
        foreach (['engine', 'dashboard', 'payables', 'bank', 'journals', 'ledger', 'assets',
            'amortization', 'closing', 'tax', 'statements', 'trial-balance', 'balance-sheet', 'income-statement', 'cash-flow'] as $page) {
            Livewire::test('pages::finance.'.$page)->assertOk();
        }
    }

    public function test_tidak_ada_faktur_uji_yang_tertinggal(): void
    {
        $this->assertSame(0, FinInvoice::withoutGlobalScopes()->where('created_by', self::USER)->count());
        $this->assertSame(0, FinVoucher::withoutGlobalScopes()->where('created_by', self::USER)->count());
    }
}
