<?php

namespace Tests\Feature;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinPeriod;
use App\Models\Acc\FinTaxRegister;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\TaxDesk as Tax;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Indonesian Tax v2 (C3d). Register uji tahun 2098 masa 1 (dibuat tes) + periode GL uji 2098-01 supaya pembayaran bisa diposting; semuanya dibersihkan lagi,
// termasuk voucher setoran pajak yang dibuat TaxEngine::pay.
class TaxDeskTest extends TestCase
{
    private const PERIOD = '2098-01';

    private ?FinPeriod $glPeriod = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    protected function tearDown(): void
    {
        $regs = FinTaxRegister::where('fiscal_year', 2098)->get();
        $voucherIds = $regs->pluck('voucher_id')->filter()->all();
        if ($voucherIds) {
            FinVoucherLine::whereIn('voucher_id', $voucherIds)->delete();
            FinVoucher::whereIn('voucher_id', $voucherIds)->delete();
        }
        FinTaxRegister::where('fiscal_year', 2098)->delete();
        $this->glPeriod?->delete();
        parent::tearDown();
    }

    private function register(array $over = []): FinTaxRegister
    {
        return FinTaxRegister::create($over + ['tax_code' => 'PPH23', 'fiscal_year' => 2098, 'period_no' => 1, 'register_code' => 'ZZ-2098-01', 'description' => 'ZZ Test PPh 23 · test',
            'base_amount' => 1000, 'credit_amount' => 0, 'payable_amount' => 1000, 'status' => 'DRAFT', 'created_by' => 'test']);
    }

    public function test_period_helpers(): void
    {
        $this->assertSame([2098, 1], Tax::ym(self::PERIOD));
        $this->assertSame(Tax::ym(Tax::defaultPeriod()), Tax::ym('garbage'));
        $options = Tax::periodOptions();
        $this->assertCount(18, $options);
        $this->assertArrayHasKey(now()->startOfMonth()->format('Y-m'), $options);
        $this->assertSame(['—', 'neutral'], Tax::dueTag(null));
        $this->assertSame('danger', Tax::dueTag(today()->subDays(3))[1]);
        $this->assertSame('warn', Tax::dueTag(today()->addDays(3))[1]);
        $this->assertSame('info', Tax::dueTag(today()->addDays(30))[1]);
    }

    public function test_generate_builds_three_monthly_registers_and_is_repeatable(): void
    {
        $this->assertSame(3, Tax::generate(self::PERIOD, 'test'));
        $this->assertSame(3, Tax::generate(self::PERIOD, 'test'));
        $this->assertSame(['PPH21', 'PPH23', 'PPN_OUT'], FinTaxRegister::where('fiscal_year', 2098)->orderBy('tax_code')->pluck('tax_code')->all());

        $c = Tax::current(self::PERIOD);
        $this->assertNotNull($c['ppn']);
        $this->assertSame('DRAFT', $c['ppn']->status);
        $kpis = Tax::kpis($c, self::PERIOD);
        $this->assertSame(['PPN Net Payable', 'PPh 21 Payable', 'PPh 23 Withheld', 'PPh Badan Provision'], array_column($kpis, 'label'));
    }

    public function test_status_helpers_and_review(): void
    {
        $r = $this->register();
        $this->assertTrue(Tax::canPay($r));
        $this->assertTrue(Tax::canFile($r));
        Tax::review($r->tax_reg_id, 'test');
        $this->assertSame('REVIEW', $r->fresh()->status);
        $r->update(['status' => 'FILED']);
        $this->assertFalse(Tax::canPay($r->fresh()));
        $this->assertFalse(Tax::canFile($r->fresh()));
        $this->assertArrayNotHasKey($r->tax_reg_id, Tax::actionable());
    }

    public function test_filing_needs_payment_first_unless_nil(): void
    {
        $r = $this->register();
        try {
            Tax::fileReturn($r->tax_reg_id, 'BPE-1', 'test');
            $this->fail('Pay first before filing.');
        } catch (ValidationException) {
            $this->assertSame('DRAFT', $r->fresh()->status);
        }
        $r->update(['payable_amount' => 0]);
        Tax::fileReturn($r->tax_reg_id, 'BPE-NIL', 'test');
        $this->assertSame('FILED', $r->fresh()->status);
        $this->assertSame('BPE-NIL', $r->fresh()->filing_ref);

        $this->expectException(ValidationException::class);
        Tax::fileReturn($r->tax_reg_id, '', 'test');
    }

    public function test_pay_validation(): void
    {
        $r = $this->register();
        foreach ([['pay_date' => '', 'ntpn' => 'X', 'pay_bank' => 1], ['pay_date' => '2098-01-15', 'ntpn' => '', 'pay_bank' => 1], ['pay_date' => '2098-01-15', 'ntpn' => 'X', 'pay_bank' => 987654321]] as $bad) {
            try {
                Tax::pay($r->tax_reg_id, $bad, 'test');
                $this->fail('Expected a ValidationException.');
            } catch (ValidationException) {
                $this->assertSame('DRAFT', $r->fresh()->status);
            }
        }
    }

    public function test_pay_posts_a_balanced_voucher_and_marks_the_register_paid(): void
    {
        $bank = array_key_first(Tax::bankOptions()) ?? $this->markTestSkipped('No bank account.');
        $this->glPeriod = FinPeriod::create(['fiscal_year' => 2098, 'period_no' => 1, 'period_name' => 'ZZ Test Tax 2098', 'start_date' => '2098-01-01', 'end_date' => '2098-01-31',
            'is_adjustment' => false, 'status' => 'OPEN', 'created_by' => 'test']);
        $r = $this->register();

        Tax::pay($r->tax_reg_id, ['pay_date' => '2098-01-15', 'ntpn' => 'ZZNTPN0000000001', 'pay_bank' => $bank], 'test');
        $r = $r->fresh();
        $this->assertSame('PAID', $r->status);
        $this->assertSame('ZZNTPN0000000001', $r->ntpn);
        $lines = FinVoucherLine::where('voucher_id', $r->voucher_id)->get();
        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.001);
        $this->assertEqualsWithDelta(1000, (float) $lines->where('account_id', $bank)->sum('credit'), 0.001);

        try {
            Tax::pay($r->tax_reg_id, ['pay_date' => '2098-01-16', 'ntpn' => 'ZZNTPN0000000002', 'pay_bank' => $bank], 'test');
            $this->fail('A paid tax cannot be paid again.');
        } catch (ValidationException) {
            $this->assertSame('ZZNTPN0000000001', $r->fresh()->ntpn);
        }
        Tax::fileReturn($r->tax_reg_id, 'BPE-PAID', 'test');
        $this->assertSame('FILED', $r->fresh()->status);
    }

    public function test_csv_and_screen_and_routes(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $r = $this->register();

        [$headers, $rows] = Tax::csv(Tax::registers('PPH23', 'ZZ'));
        $this->assertSame('Register', $headers[0]);
        $this->assertContains('ZZ-2098-01', array_column($rows, 0));

        $this->actingAs($admin)->get(route('v2.screen', 'acc-tax').'?period='.self::PERIOD.'&q=ZZ')->assertOk()->assertSee('Tax Register');
        $this->actingAs($admin)->get(route('v2.screen', 'acc-tax').'?period='.self::PERIOD.'&reg='.$r->tax_reg_id)->assertOk()->assertSee('Register Actions');
        $csv = $this->actingAs($admin)->get(route('v2.acc.tax.csv', ['q' => 'ZZ']));
        $csv->assertOk();
        $this->assertStringContainsString('ZZ-2098-01', $csv->streamedContent());

        $this->actingAs($admin)->post(route('v2.acc.tax.review', $r->tax_reg_id), ['period' => self::PERIOD])->assertRedirect()->assertSessionHas('v2_flash');
        $this->assertSame('REVIEW', $r->fresh()->status);
        $this->actingAs($admin)->post(route('v2.acc.tax.file', $r->tax_reg_id), ['period' => self::PERIOD, 'filing_ref' => 'X'])->assertSessionHasErrors('finance');
        $this->actingAs($admin)->post(route('v2.acc.tax.pay', $r->tax_reg_id), ['period' => self::PERIOD])->assertSessionHasErrors();
        $this->actingAs($admin)->post(route('v2.acc.tax.generate'), ['period' => self::PERIOD])->assertRedirect()->assertSessionHas('v2_flash');
        $this->assertNotNull(FinAccount::where('account_code', '212100')->first());
    }
}
