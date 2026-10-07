<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Services\Acc\LedgerService;
use App\Support\V2\Data\FinancialStatements as FS;
use Devlib\Components\Statement;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

// Financial Reports v2 (C2): bentuk baris / KPI / CSV dari LedgerService + objek DevLib x-dl::statement + rute layar dan unduhan. Tidak menulis ke database.
class FinancialStatementsTest extends TestCase
{
    private const AS_OF = '2026-06-30';

    private function value(array $rows, string $label): float
    {
        return (float) collect($rows)->firstWhere('label', t($label))['values'][0];
    }

    public function test_statement_component_renders_kinds_columns_and_negative_amounts(): void
    {
        $html = Blade::render('<x-dl::statement title="T" :columns="[\'2026\', \'2025\']" :rows="$rows" />', ['rows' => [
            ['kind' => 'group', 'label' => 'ASSETS'], ['label' => 'Cash', 'values' => [1500, -200]], ['kind' => 'total', 'label' => 'Total', 'values' => [1300, null]],
            ['kind' => 'net', 'label' => 'NET', 'values' => [1, 2]], ['kind' => 'note', 'label' => 'Memo'], ['kind' => 'bogus', 'label' => 'Odd', 'values' => [0, 0]]]]);
        foreach (['dl-stmt', 'is-group', 'is-line', 'is-total', 'is-net', 'is-note', 'is-neg', '2026', '2025'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertSame(1, substr_count($html, 'is-neg'), 'only the negative amount is flagged');
        $this->assertSame('line', (new Statement)->kind(['kind' => 'bogus']));
        $this->assertSame('', (new Statement)->money(null));
    }

    public function test_balance_sheet_rows_follow_ledger_totals_and_comparison_is_a_difference(): void
    {
        $bs = FS::ledger()->balanceSheet(self::AS_OF);
        $prior = FS::ledger()->balanceSheet('2025-12-31');
        $rows = FS::balanceSheetRows($bs);

        $this->assertEqualsWithDelta($bs['total_assets'], $this->value($rows, 'TOTAL ASSETS'), 0.001);
        $this->assertEqualsWithDelta($bs['total_le'], $this->value($rows, 'TOTAL LIABILITIES AND EQUITY'), 0.001);
        $this->assertEqualsWithDelta($bs['total_equity'], $this->value($rows, 'Total equity'), 0.001);
        $this->assertSame('net', end($rows)['kind']);

        foreach (FS::balanceSheetComparison($bs, $prior) as $r) {
            $this->assertEqualsWithDelta($r['values'][0] - $r['values'][1], $r['values'][2], 0.01);
        }
        $this->assertCount(2, FS::balanceSheetRows($bs, $prior)[2]['values']);
    }

    public function test_income_statement_rows_and_kpis(): void
    {
        $is = FS::ledger()->incomeStatement('2026-01-01', self::AS_OF);
        $rows = FS::incomeStatementRows($is);

        $this->assertEqualsWithDelta($is['net_profit'], $this->value($rows, 'NET PROFIT FOR THE PERIOD'), 0.001);
        $this->assertEqualsWithDelta($is['gross_profit'], $this->value($rows, 'Gross profit'), 0.001);

        $kpis = FS::incomeStatementKpis($is, '2026-01-01', self::AS_OF);
        $this->assertSame(['Revenue', 'Gross Profit', 'Operating Profit', 'Net Profit'], array_column($kpis, 'label'));
        $this->assertSame('money', $kpis[0]['format']);
    }

    public function test_cash_flow_rows_sections_and_reconciliation(): void
    {
        $cf = FS::ledger()->cashFlow('2026-01-01', self::AS_OF);
        $rows = FS::cashFlowRows($cf);

        $this->assertEqualsWithDelta($cf['operating'], $this->value($rows, 'Net cash generated from operations'), 0.001);
        $this->assertEqualsWithDelta($cf['closing_cash'], $this->value($rows, 'Cash at end of period'), 0.001);
        $this->assertSame(0.0, FS::cashDifference(['opening_cash' => 100.0, 'net_change' => 50.0, 'closing_cash' => 150.0]));
        $this->assertSame(10.0, FS::cashDifference(['opening_cash' => 100.0, 'net_change' => 50.0, 'closing_cash' => 140.0]));
        $this->assertSame(0.0, FS::balanceDifference(['total_assets' => 5.0, 'total_le' => 5.004]));
        $this->assertSame(-5.0, FS::balanceDifference(['total_assets' => 5.0, 'total_le' => 10.0]));
    }

    public function test_summary_and_trend_shapes(): void
    {
        $bs = FS::ledger()->balanceSheet(self::AS_OF);
        $is = FS::ledger()->incomeStatement('2026-01-01', self::AS_OF);
        $cf = FS::ledger()->cashFlow('2026-01-01', self::AS_OF);
        [$b, $i, $c] = FS::summaryRows($bs, $is, $cf);
        $this->assertEqualsWithDelta($bs['total_assets'], $this->value($b, 'TOTAL ASSETS'), 0.001);
        $this->assertEqualsWithDelta($is['net_profit'], $this->value($i, 'NET PROFIT'), 0.001);
        $this->assertEqualsWithDelta($cf['net_change'], $this->value($c, 'NET INCREASE IN CASH'), 0.001);
        $this->assertCount(4, FS::summaryKpis($bs, $is, FS::priorYearIncome(self::AS_OF)));

        $trend = FS::operatingCashTrend('2026-01-01', self::AS_OF);
        $this->assertCount(6, $trend['labels']);
        $this->assertCount(6, $trend['series'][0]['data']);
        $months = FS::monthlyIncome('2026-01-01', self::AS_OF);
        $this->assertCount(6, $months);
        $this->assertEqualsWithDelta($is['net_profit'], array_sum(array_column($months, 'net')), 0.05);
    }

    public function test_csv_shapes_match_v1(): void
    {
        $ledger = FS::ledger();
        [$h, $rows] = FS::balanceSheetCsv($ledger->balanceSheet(self::AS_OF), $ledger->balanceSheet('2025-12-31'), self::AS_OF, '2025-12-31');
        $this->assertSame(['Line', 'As at '.self::AS_OF, 'As at 2025-12-31'], $h);
        $this->assertCount(15, $rows);
        [$h, $rows] = FS::incomeStatementCsv($ledger->incomeStatement('2026-01-01', self::AS_OF));
        $this->assertSame(['Line', 'Amount (IDR)'], $h);
        $this->assertCount(11, $rows);
        [, $rows] = FS::cashFlowCsv($ledger->cashFlow('2026-01-01', self::AS_OF));
        $this->assertCount(count(LedgerService::CF_LABELS) + 6, $rows);
    }

    public function test_screens_and_downloads_work_for_a_finance_user(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $q = '?as_of='.self::AS_OF.'&from=2026-01-01&to='.self::AS_OF;
        foreach (['acc-statements', 'acc-rpt-balance-sheet', 'acc-rpt-income-statement', 'acc-rpt-cash-flow'] as $slug) {
            $this->actingAs($admin)->get(route('v2.screen', $slug).$q)->assertOk()->assertSee('dl-stmt__table', false);
        }
        $this->actingAs($admin)->get(route('v2.screen', 'acc-rpt-balance-sheet').$q.'&compare=1')->assertOk();
        $this->actingAs($admin)->get(route('v2.screen', 'acc-rpt-income-statement').$q.'&monthly=1')->assertOk();

        foreach (['balance-sheet', 'income-statement', 'cash-flow'] as $report) {
            $res = $this->actingAs($admin)->get(route('v2.acc.statement.csv', $report).$q);
            $res->assertOk();
            $body = $res->streamedContent();
            $this->assertStringStartsWith("\u{FEFF}Line,", $body);
        }
        $this->actingAs($admin)->get(route('v2.acc.statement.csv', 'nonsense'))->assertNotFound();
    }
}
