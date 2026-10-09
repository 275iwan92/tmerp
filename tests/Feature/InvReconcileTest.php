<?php

namespace Tests\Feature;

use App\Models\Inventory\InvReconcileLog;
use App\Models\Sys\SysConstant;
use App\Services\Acc\JournalPostingService;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\Valuation\ReconcileService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Rekonsiliasi harian: saldo akun persediaan (buku besar) vs nilai kartu stok per kelas, ditulis ke inv_reconcile_log.
// Jalankan terhadap database dummy: powershell -NoProfile -File tools/test-ddt.ps1 tests/Feature/InvReconcileTest.php
class InvReconcileTest extends TestCase
{
    private const USER = 'recontest';

    private const CODE = 'TSTRECON1';

    private ?string $savedAccounts = null;

    private ?string $savedSfg = null;

    private int $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = (int) DB::connection('tm2acc')->table('fin_account')->insertGetId(['account_code' => self::CODE, 'account_name' => 'Test reconcile', 'account_type' => 'ASSET',
            'normal_balance' => 'DEBIT', 'is_postable' => 1, 'activate' => 1, 'created_by' => self::USER, 'created_at' => now(), 'data_origin' => 'LIVE']);
        $this->savedAccounts = SysConstant::text('INV_RECON_ACCTS_RM');
        $this->savedSfg = SysConstant::text('INV_RECON_ACCTS_SFG');
        $this->constant('INV_RECON_ACCTS_RM', self::CODE);
        $this->constant('INV_RECON_ACCTS_SFG', self::CODE.'X');
    }

    protected function tearDown(): void
    {
        $this->constant('INV_RECON_ACCTS_RM', $this->savedAccounts);
        $this->constant('INV_RECON_ACCTS_SFG', $this->savedSfg);
        $vouchers = DB::connection('tm2acc')->table('fin_voucher')->where('created_by', self::USER)->pluck('voucher_id');
        DB::connection('tm2acc')->table('fin_voucher_line')->whereIn('voucher_id', $vouchers)->delete();
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $vouchers)->delete();
        DB::connection('tm2acc')->table('fin_account')->where('account_code', self::CODE)->delete();
        InvReconcileLog::query()->where('created_by', self::USER)->delete();
        parent::tearDown();
    }

    private function constant(string $key, ?string $value): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $key)->update(['constant_text' => $value]);
    }

    private function book(float $amount): void
    {
        app(JournalPostingService::class)->post('GEN', 'RECON_TEST', 1, 'recon test', [
            ['account_id' => $this->account, 'debit' => $amount],
            ['account_id' => (int) DB::connection('tm2acc')->table('fin_account')->where('account_code', '211950')->value('account_id'), 'credit' => $amount],
        ], self::USER, now()->toDateString());
    }

    private function cardValue(string $class, CarbonImmutable $date): float
    {
        StockLedger::forget();

        return round(collect(StockLedger::between($date, $date)->items())->where('class', $class)->sum('closing_value'), 2);
    }

    private function rm(array $rows): array
    {
        return collect($rows)->firstWhere('stock_class', 'RM');
    }

    public function test_difference_is_ledger_minus_stock_card_and_is_flagged(): void
    {
        $today = CarbonImmutable::today();
        $cards = $this->cardValue('RM', $today);
        $this->book(1234.56);

        $rm = $this->rm(app(ReconcileService::class)->run($today, self::USER, false));

        $this->assertSame(1234.56, $rm['gl_value']);
        $this->assertSame($cards, $rm['ledger_value']);
        $this->assertSame(round(1234.56 - $cards - $rm['landed_pending'], 2), $rm['difference']);
        $this->assertSame(abs($rm['difference']) > 1.0 ? 'DIFF' : 'OK', $rm['status']);
    }

    public function test_a_ledger_equal_to_the_cards_is_ok(): void
    {
        $today = CarbonImmutable::today();
        $service = app(ReconcileService::class);
        $this->book(1.0);
        $gap = $this->rm($service->run($today, self::USER, false));
        $this->book(round($gap['ledger_value'] + $gap['landed_pending'] - $gap['gl_value'], 2));

        $rm = $this->rm($service->run($today, self::USER, false));

        $this->assertSame(0.0, $rm['difference']);
        $this->assertSame('OK', $rm['status']);
    }

    public function test_the_result_is_saved_once_per_day_and_class(): void
    {
        $today = CarbonImmutable::today();
        $service = app(ReconcileService::class);
        $service->run($today, self::USER);
        $this->book(500);
        $service->run($today, self::USER);

        $logs = InvReconcileLog::query()->where('recon_date', $today->toDateString())->where('created_by', self::USER)->get();

        $this->assertSame(['FG', 'RM', 'SFG'], $logs->pluck('stock_class')->sort()->values()->all());
        $this->assertSame(500.0, $logs->firstWhere('stock_class', 'RM')->gl_value, 'the second run replaces the first');
    }

    public function test_the_ledger_only_counts_posted_vouchers_up_to_the_date(): void
    {
        $this->book(700);
        $service = app(ReconcileService::class);

        $this->assertSame(700.0, $service->glValue([$this->account], CarbonImmutable::today()));
        $this->assertSame(0.0, $service->glValue([$this->account], CarbonImmutable::today()->subDay()));
        $this->assertSame(0.0, $service->glValue([], CarbonImmutable::today()));
    }

    public function test_the_command_prints_the_comparison_without_saving_when_asked(): void
    {
        $before = InvReconcileLog::query()->count();

        Artisan::call('inv:reconcile', ['--no-save' => true]);

        $this->assertStringContainsString('Stock card', Artisan::output());
        $this->assertSame($before, InvReconcileLog::query()->count());
    }
}
