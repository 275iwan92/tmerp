<?php

namespace Tests\Feature;

use App\Models\Inventory\InvRepostLog;
use App\Services\Inventory\Valuation\RepostService;
use App\Services\Inventory\Valuation\ValuationAccounts;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Jurnal koreksi bertumpuk di luar landed cost: penilaian ulang baris keluar (Average berubah karena transaksi mundur) dan koreksi jurnal umum.
// Jalankan terhadap database dummy: powershell -NoProfile -File tools/test-ddt.ps1 tests/Feature/LandedCostRepostTest.php
class LandedCostRepostTest extends TestCase
{
    private const USER = 'reposttest';

    private int $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->material = (int) DB::connection('tm2mst')->table('mat_raw')->whereNotNull('stock_asset_account_id')->whereNotNull('cogs_account_id')->min('material_id');
        if (! $this->material) {
            $this->markTestSkipped('Needs a raw material with inventory and COGS accounts.');
        }
    }

    protected function tearDown(): void
    {
        $vouchers = DB::connection('tm2acc')->table('fin_voucher')->where('created_by', self::USER)->pluck('voucher_id');
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $vouchers)->update(['reversal_of_id' => null, 'reversed_by_id' => null]);
        DB::connection('tm2acc')->table('fin_voucher_line')->whereIn('voucher_id', $vouchers)->delete();
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $vouchers)->delete();
        InvRepostLog::query()->where('created_by', self::USER)->delete();
        parent::tearDown();
    }

    public function test_a_higher_issue_cost_debits_cogs_and_credits_inventory_and_is_logged(): void
    {
        $voucher = app(RepostService::class)->revalueIssued($this->material, 571.43, 'SO26-TEST', self::USER);

        $lines = $voucher->lines()->get();
        $this->assertSame(571.43, round((float) $lines->firstWhere('account_id', ValuationAccounts::cogsFor($this->material))->debit, 2));
        $this->assertSame(571.43, round((float) $lines->firstWhere('account_id', ValuationAccounts::stockFor($this->material))->credit, 2));
        $log = InvRepostLog::query()->where('correction_voucher_id', $voucher->voucher_id)->first();
        $this->assertSame(['REVALUATION', 571.43], [$log->repost_kind, $log->amount_delta]);
    }

    public function test_a_lower_issue_cost_flips_the_journal_and_no_change_posts_nothing(): void
    {
        $service = app(RepostService::class);

        $voucher = $service->revalueIssued($this->material, -100, 'SO26-TEST', self::USER);

        $lines = $voucher->lines()->get();
        $this->assertSame(100.0, round((float) $lines->firstWhere('account_id', ValuationAccounts::stockFor($this->material))->debit, 2));
        $this->assertNull($service->revalueIssued($this->material, 0.0, 'SO26-TEST', self::USER));
    }

    public function test_a_correction_posts_only_the_difference_and_an_identical_journal_posts_nothing(): void
    {
        $service = app(RepostService::class);
        $stock = ValuationAccounts::stockFor($this->material);
        $cogs = ValuationAccounts::cogsFor($this->material);
        $original = $service->revalueIssued($this->material, 1000, 'SO26-TEST', self::USER);
        $same = [['account_id' => $cogs, 'debit' => 1000], ['account_id' => $stock, 'credit' => 1000]];

        $this->assertNull($service->correct($original, $same, 'Nothing changed in the cost', self::USER));

        $correction = $service->correct($original, [['account_id' => $cogs, 'debit' => 1200], ['account_id' => $stock, 'credit' => 1200]], 'Cost was 1,200 not 1,000', self::USER);
        $this->assertSame(200.0, round((float) $correction->lines()->sum('debit'), 2));
        $this->assertSame(1000.0, round((float) $original->refresh()->lines()->sum('debit'), 2), 'the original journal stays');
    }
}
