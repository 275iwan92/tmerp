<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Services\Acc\LedgerService;
use App\Support\Localization\Dictionary;
use Carbon\Carbon;
use Devlib\Locale;

// Finance > Financial Reports (v2): Neraca, Laba Rugi, Arus Kas dan Ringkasan dalam satu tempat, semua dari LedgerService (voucher POSTED saja, rumus sama dengan v1).
// Kelas ini hanya menyusun bentuk yang dibaca objek DevLib: baris x-dl::statement, kartu x-dl::kpi, grafik x-dl::chart, dan isi CSV unduhan.
// Angka laporan ber-tanda: pendapatan positif, beban negatif (seperti LedgerService).
final class FinancialStatements
{
    public static function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    /** Awal tahun dari tanggal posisi (laporan YTD). */
    public static function yearStart(string $date): string
    {
        return date('Y-01-01', strtotime($date));
    }

    // ---------- baris laporan ----------

    private static function row(string $label, array $values, string $kind = 'line'): array
    {
        return ['kind' => $kind, 'label' => t($label), 'values' => $values];
    }

    private static function head(string $label, string $kind = 'group'): array
    {
        return ['kind' => $kind, 'label' => t($label)];
    }

    /** Ambil satu baris dari dua laporan (periode ini dan pembanding), pembanding opsional. */
    private static function pair(array $now, ?array $then, string $key): array
    {
        return $then === null ? [$now[$key]] : [$now[$key], $then[$key]];
    }

    /** @return list<array<string, mixed>> Neraca; $then = neraca pembanding (kolom kedua) */
    public static function balanceSheetRows(array $bs, ?array $then = null): array
    {
        $p = fn (string $label, string $key, string $kind = 'line') => self::row($label, self::pair($bs, $then, $key), $kind);

        return [
            self::head('ASSETS'), self::head('Current assets', 'line'),
            $p('Cash and cash equivalents', 'CASH'), $p('Trade accounts receivable, net', 'AR'), $p('Inventories', 'INVENTORY'),
            $p('Prepayments, tax and other current assets', 'OTHER_CA'), $p('Total current assets', 'current_assets', 'total'),
            self::head('Non-current assets', 'line'),
            $p('Property, plant and equipment, net', 'PPE'), $p('Intangible assets, net', 'INTANGIBLE'), $p('TOTAL ASSETS', 'total_assets', 'total'),
            self::head('LIABILITIES'),
            $p('Trade accounts payable', 'AP'), $p('Taxes, accruals and other liabilities', 'ACCRUED'), $p('Bank loans', 'BANK_LOAN'), $p('Total liabilities', 'total_liabilities', 'total'),
            self::head('EQUITY'),
            $p('Share capital', 'SHARE_CAPITAL'), $p('Retained earnings (incl. current year result)', 'RETAINED'), $p('Total equity', 'total_equity', 'total'),
            $p('TOTAL LIABILITIES AND EQUITY', 'total_le', 'net'),
        ];
    }

    /** @return list<array<string, mixed>> Laba Rugi */
    public static function incomeStatementRows(array $is): array
    {
        $p = fn (string $label, string $key, string $kind = 'line') => self::row($label, [$is[$key]], $kind);

        return [
            self::head('REVENUE'), $p('Sales revenue (net of discounts and returns)', 'REVENUE'), $p('Total revenue', 'REVENUE', 'total'),
            self::head('COST OF REVENUE'), $p('Cost of goods and services sold', 'COGS'), $p('Gross profit', 'gross_profit', 'total'),
            self::head('OPERATING EXPENSES'), $p('Employee benefits expense', 'EMPLOYEE'), $p('Sales and marketing expense', 'SELLING'), $p('General and administrative expense', 'GA'),
            $p('Operating profit', 'operating_profit', 'total'),
            $p('Other income / (expense), net', 'OTHER'), $p('Profit before income tax', 'profit_before_tax', 'total'),
            $p('Income tax expense', 'INCOME_TAX'), $p('NET PROFIT FOR THE PERIOD', 'net_profit', 'net'),
        ];
    }

    /** @return list<array<string, mixed>> perbandingan Neraca: periode ini, pembanding, selisih */
    public static function balanceSheetComparison(array $bs, array $prior): array
    {
        $rows = [];
        foreach (['CASH', 'AR', 'INVENTORY', 'OTHER_CA', 'PPE', 'INTANGIBLE', 'total_assets', 'AP', 'ACCRUED', 'BANK_LOAN', 'SHARE_CAPITAL', 'RETAINED', 'total_le'] as $k) {
            $rows[] = ['kind' => in_array($k, ['total_assets', 'total_le'], true) ? 'total' : 'line', 'label' => t(LedgerService::FS_LABELS[$k] ?? ucwords(str_replace('_', ' ', $k))),
                'values' => [$bs[$k], $prior[$k], round($bs[$k] - $prior[$k], 2)]];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> Arus Kas (metode tidak langsung) */
    public static function cashFlowRows(array $cf): array
    {
        $p = fn (string $label, string $key, string $kind = 'line') => self::row($label, [$cf[$key]], $kind);

        return [
            self::head('Cash flows from operating activities'),
            $p('Profit before income tax', 'PROFIT'), $p('Depreciation and amortization', 'DA'), $p('(Increase) / decrease in trade receivables', 'WC_AR'),
            $p('(Increase) / decrease in inventories', 'WC_INV'), $p('(Increase) / decrease in other current assets', 'WC_OTHER_CA'),
            $p('Increase / (decrease) in trade payables', 'WC_AP'), $p('Increase / (decrease) in accruals and tax liabilities', 'WC_OTHER_CL'),
            $p('Income tax paid', 'TAX'), $p('Net cash generated from operations', 'operating', 'total'),
            self::head('Cash flows from investing activities'),
            $p('Purchase of property, plant, equipment and intangibles', 'INV_PPE'), $p('Net cash used in investing activities', 'investing', 'total'),
            self::head('Cash flows from financing activities'),
            $p('Proceeds / (repayment) of bank loans', 'FIN_LOAN'), $p('Capital contribution / (dividends)', 'FIN_EQUITY'), $p('Net cash from financing activities', 'financing', 'total'),
            $p('NET INCREASE / (DECREASE) IN CASH', 'net_change', 'net'),
            $p('Cash at beginning of period', 'opening_cash'), $p('Cash at end of period', 'closing_cash', 'total'),
        ];
    }

    /** @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>} ringkasan Neraca, Laba Rugi, Arus Kas */
    public static function summaryRows(array $bs, array $is, array $cf): array
    {
        $b = fn (string $label, float $v, string $kind = 'line') => self::row($label, [$v], $kind);

        return [
            [self::head('ASSETS'), $b('Cash and cash equivalents', $bs['CASH']), $b('Trade accounts receivable, net', $bs['AR']),
                $b('Inventories and other current assets', $bs['INVENTORY'] + $bs['OTHER_CA']), $b('Property, plant, equipment and intangibles, net', $bs['PPE'] + $bs['INTANGIBLE']),
                $b('TOTAL ASSETS', $bs['total_assets'], 'total'), self::head('LIABILITIES & EQUITY'), $b('Total liabilities', $bs['total_liabilities']),
                $b('Total equity', $bs['total_equity']), $b('TOTAL LIABILITIES AND EQUITY', $bs['total_le'], 'net')],
            [self::head('REVENUE'), $b('Sales revenue', $is['REVENUE']), $b('Cost of goods and services sold', $is['COGS']), $b('Gross profit', $is['gross_profit'], 'total'),
                self::head('OPERATING EXPENSES'), $b('Employee benefits expense', $is['EMPLOYEE']), $b('Sales and marketing expense', $is['SELLING']),
                $b('General and administrative expense', $is['GA']), $b('Operating profit', $is['operating_profit'], 'total'), $b('Income tax expense', $is['INCOME_TAX']),
                $b('NET PROFIT', $is['net_profit'], 'net')],
            [self::head('OPERATING ACTIVITIES'), $b('Net cash generated from operations', $cf['operating']), self::head('INVESTING ACTIVITIES'),
                $b('Purchase of property, plant and equipment', $cf['investing']), self::head('FINANCING ACTIVITIES'), $b('Loans and equity movements', $cf['financing']),
                $b('NET INCREASE IN CASH', $cf['net_change'], 'net')],
        ];
    }

    // ---------- kartu KPI (props x-dl::kpi) ----------

    private static function kpi(string $label, float $value, string $icon, string $tone, ?float $previous = null, ?string $note = null, string $goodWhen = 'up'): array
    {
        return ['label' => $label, 'value' => $value, 'format' => 'money', 'previous' => $previous, 'icon' => $icon, 'tone' => $tone, 'note' => $note, 'goodWhen' => $goodWhen];
    }

    private static function ratio(float $a, float $b, int $decimals = 2): ?string
    {
        return $b != 0.0 ? Locale::number($a / $b, $decimals).'x' : null;
    }

    private static function margin(float $value, float $revenue): string
    {
        return $revenue > 0 ? Locale::percent($value / $revenue * 100) : '—';
    }

    /** @return list<array<string, mixed>> */
    public static function balanceSheetKpis(array $bs, array $prior): array
    {
        $de = self::ratio($bs['total_liabilities'], $bs['total_equity']);
        $cr = self::ratio($bs['current_assets'], $bs['current_liabilities']);

        return [
            self::kpi('Total Assets', $bs['total_assets'], 'fa-gem', 'accent', $prior['total_assets']),
            self::kpi('Total Liabilities', $bs['total_liabilities'], 'fa-file-contract', 'warn', null, $de ? tf('Debt-to-equity: :ratio', ['ratio' => $de]) : null, 'down'),
            self::kpi('Total Equity', $bs['total_equity'], 'fa-landmark', 'success', $prior['total_equity']),
            ['label' => 'Current Ratio', 'value' => (float) ($bs['current_liabilities'] != 0 ? $bs['current_assets'] / $bs['current_liabilities'] : 0), 'format' => 'number', 'previous' => null,
                'icon' => 'fa-arrows-left-right', 'tone' => 'violet', 'note' => $cr ? t('Current assets / current liabilities') : t('No current liabilities'), 'goodWhen' => 'up'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function incomeStatementKpis(array $is, string $from, string $to): array
    {
        return [
            self::kpi('Revenue', $is['REVENUE'], 'fa-arrow-trend-up', 'accent', null, Locale::d($from).' — '.Locale::d($to)),
            self::kpi('Gross Profit', $is['gross_profit'], 'fa-chart-pie', 'success', null, tf('Gross margin :pct', ['pct' => self::margin($is['gross_profit'], $is['REVENUE'])])),
            self::kpi('Operating Profit', $is['operating_profit'], 'fa-table', 'info', null, tf('Margin :pct', ['pct' => self::margin($is['operating_profit'], $is['REVENUE'])])),
            self::kpi('Net Profit', $is['net_profit'], 'fa-circle-check', $is['net_profit'] >= 0 ? 'success' : 'danger', null, $is['net_profit'] >= 0 ? t('Profit') : t('Loss')),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function cashFlowKpis(array $cf, string $from): array
    {
        return [
            self::kpi('Opening Cash', $cf['opening_cash'], 'fa-vault', 'info', null, Locale::d($from)),
            self::kpi('Net Operating Cash', $cf['operating'], 'fa-arrow-trend-up', $cf['operating'] >= 0 ? 'success' : 'danger', null, $cf['operating'] >= 0 ? t('Positive') : t('Negative')),
            self::kpi('Net Investing Cash', $cf['investing'], 'fa-arrow-trend-down', 'warn', null, t('CAPEX and intangibles')),
            self::kpi('Closing Cash', $cf['closing_cash'], 'fa-circle-check', 'accent', null, tf(':change change', ['change' => Locale::money($cf['net_change'])])),
        ];
    }

    /** @return list<array<string, mixed>> Ringkasan: Aset, Kewajiban, Pendapatan YTD, Laba bersih YTD (+ pertumbuhan vs tahun lalu) */
    public static function summaryKpis(array $bs, array $is, array $priorIs): array
    {
        $de = self::ratio($bs['total_liabilities'], $bs['total_equity']);

        return [
            self::kpi('Total Assets', $bs['total_assets'], 'fa-gem', 'accent'),
            self::kpi('Total Liabilities', $bs['total_liabilities'], 'fa-file-contract', 'warn', null, $de ? tf('Debt-to-equity: :ratio', ['ratio' => $de]) : null, 'down'),
            self::kpi('Revenue YTD', $is['REVENUE'], 'fa-arrow-trend-up', 'success', $priorIs['REVENUE']),
            self::kpi('Net Profit YTD', $is['net_profit'], 'fa-circle-check', $is['net_profit'] >= 0 ? 'success' : 'danger', $priorIs['net_profit']),
        ];
    }

    // ---------- periode pembanding / bulanan ----------

    /** Laba Rugi tahun lalu untuk rentang yang sama (YTD sampai tanggal yang sama setahun sebelumnya). */
    public static function priorYearIncome(string $asOf): array
    {
        $y = (int) date('Y', strtotime($asOf)) - 1;

        return self::ledger()->incomeStatement("{$y}-01-01", date('Y-m-d', strtotime($asOf.' -1 year')));
    }

    /** Nama bulan 3 huruf mengikuti bahasa tampilan (kamus date.month_short). */
    private static function month(Carbon $d): string
    {
        return Dictionary::t($d->format('M'), 'date.month_short');
    }

    /** @return list<array<string, mixed>> baris tabel perbandingan bulanan Laba Rugi */
    public static function monthlyIncome(string $from, string $to): array
    {
        $svc = self::ledger();
        $rows = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to);
        while ($cursor->lte($end)) {
            $m = $svc->incomeStatement($cursor->toDateString(), min($cursor->copy()->endOfMonth(), $end)->toDateString());
            $rows[] = ['month' => self::month($cursor).' '.$cursor->year, 'revenue' => $m['REVENUE'], 'cogs' => $m['COGS'], 'gross' => $m['gross_profit'],
                'opex' => $m['EMPLOYEE'] + $m['SELLING'] + $m['GA'], 'net' => $m['net_profit']];
            $cursor->addMonth();
        }

        return $rows;
    }

    /** @return array{labels: list<string>, series: list<array<string, mixed>>} arus kas operasi per bulan untuk x-dl::chart */
    public static function operatingCashTrend(string $from, string $to): array
    {
        $year = (int) substr($from, 0, 4);
        $monthly = self::ledger()->monthlyOperatingCash($year, (int) substr($to, 5, 2));
        $labels = array_map(fn ($m) => self::month(Carbon::create($year, $m, 1)), array_keys($monthly));

        return ['labels' => $labels, 'series' => [['name' => t('Operating cash'), 'data' => array_values($monthly)]]];
    }

    // ---------- peringatan ----------

    /** Selisih Neraca (Aset - Kewajiban & Ekuitas); 0 = seimbang. */
    public static function balanceDifference(array $bs): float
    {
        $d = round($bs['total_assets'] - $bs['total_le'], 2);

        return abs($d) > 0.01 ? $d : 0.0;
    }

    /** Selisih rekonsiliasi kas (kas awal + perubahan - kas akhir); != 0 berarti ada akun tanpa klasifikasi arus kas. */
    public static function cashDifference(array $cf): float
    {
        $d = round($cf['opening_cash'] + $cf['net_change'] - $cf['closing_cash'], 2);

        return abs($d) > 0.01 ? $d : 0.0;
    }

    // ---------- CSV ----------

    /** @return array{0: list<string>, 1: list<list<mixed>>} header + baris untuk unduhan Neraca (kolom pembanding ikut). */
    public static function balanceSheetCsv(array $bs, array $prior, string $asOf, string $compareTo): array
    {
        $rows = [];
        foreach (['CASH', 'AR', 'INVENTORY', 'OTHER_CA', 'PPE', 'INTANGIBLE', 'total_assets', 'AP', 'ACCRUED', 'BANK_LOAN', 'total_liabilities', 'SHARE_CAPITAL', 'RETAINED', 'total_equity', 'total_le'] as $k) {
            $rows[] = [LedgerService::FS_LABELS[$k] ?? ucwords(str_replace('_', ' ', $k)), $bs[$k] ?? 0, $prior[$k] ?? 0];
        }

        return [['Line', 'As at '.$asOf, 'As at '.$compareTo], $rows];
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    public static function incomeStatementCsv(array $is): array
    {
        $rows = [];
        foreach (['REVENUE', 'COGS', 'gross_profit', 'EMPLOYEE', 'SELLING', 'GA', 'operating_profit', 'OTHER', 'profit_before_tax', 'INCOME_TAX', 'net_profit'] as $k) {
            $rows[] = [LedgerService::FS_LABELS[$k] ?? ucwords(str_replace('_', ' ', $k)), $is[$k]];
        }

        return [['Line', 'Amount (IDR)'], $rows];
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    public static function cashFlowCsv(array $cf): array
    {
        $rows = [];
        foreach (LedgerService::CF_LABELS as $k => $label) {
            $rows[] = [$label, $cf[$k]];
        }
        foreach (['operating', 'investing', 'financing', 'net_change', 'opening_cash', 'closing_cash'] as $k) {
            $rows[] = [ucwords(str_replace('_', ' ', $k)), $cf[$k]];
        }

        return [['Line', 'Amount (IDR)'], $rows];
    }
}
