<?php

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Support\DataOrigin\Visible;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Sumber angka SEMUA laporan keuangan & dashboard: hanya voucher POSTED yang
// tidak dibatalkan (blueprint: "Financial statements are generated only from
// posted GL data"). Pemetaan baris laporan dari fin_account.fs_line/cf_line.
class LedgerService
{
    public const FS_LABELS = [
        'CASH' => 'Cash and cash equivalents',
        'AR' => 'Trade accounts receivable, net',
        'INVENTORY' => 'Inventories',
        'OTHER_CA' => 'Other current assets (VAT, prepayments, advances)',
        'PPE' => 'Property, plant and equipment, net',
        'INTANGIBLE' => 'Intangible assets, net',
        'AP' => 'Trade accounts payable',
        'ACCRUED' => 'Accrued expenses, taxes and other liabilities',
        'BANK_LOAN' => 'Bank loans',
        'SHARE_CAPITAL' => 'Share capital',
        'RETAINED' => 'Retained earnings (incl. current year profit)',
        'REVENUE' => 'Sales revenue',
        'COGS' => 'Cost of goods sold',
        'EMPLOYEE' => 'Employee benefits expense',
        'SELLING' => 'Sales and marketing expense',
        'GA' => 'General and administrative expense',
        'OTHER' => 'Other income / (expense), net',
        'INCOME_TAX' => 'Income tax expense',
    ];

    public const CF_LABELS = [
        'PROFIT' => 'Profit before income tax',
        'DA' => 'Depreciation and amortization',
        'WC_AR' => '(Increase) / decrease in trade receivables',
        'WC_INV' => '(Increase) / decrease in inventories',
        'WC_OTHER_CA' => '(Increase) / decrease in other current assets',
        'WC_AP' => 'Increase / (decrease) in trade payables',
        'WC_OTHER_CL' => 'Increase / (decrease) in accrued & other liabilities',
        'TAX' => 'Income tax paid',
        'INV_PPE' => 'Purchase of property, plant, equipment & intangibles',
        'FIN_LOAN' => 'Proceeds / (repayment) of bank loans',
        'FIN_EQUITY' => 'Capital contribution / (dividends)',
    ];

    private ?Collection $accounts = null;

    public function accounts(): Collection
    {
        return $this->accounts ??= FinAccount::withoutGlobalScopes()->orderBy('account_code')->get()->keyBy('account_id');
    }

    // account_id => ['debit' => x, 'credit' => y] untuk voucher POSTED dalam rentang.
    public function movements(?string $from, string $to, array $excludeKinds = []): Collection
    {
        $q = DB::connection('tm2acc')->table('fin_voucher_line as l')
            ->join('fin_voucher as v', 'v.voucher_id', '=', 'l.voucher_id')
            ->where('v.status', 'POSTED')->where('v.stat_cancel', 0)
            ->where('v.entry_date', '<=', $to)
            ->when($from, fn ($q) => $q->where('v.entry_date', '>=', $from))
            ->when($excludeKinds, fn ($q) => $q->where(fn ($w) => $w->whereNull('v.journal_kind')->orWhereNotIn('v.journal_kind', $excludeKinds)))
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit) debit, SUM(l.credit) credit');
        Visible::apply($q, 'tm2acc', 'fin_voucher', 'v');

        return $q->get()->mapWithKeys(fn ($r) => [$r->account_id => ['debit' => (float) $r->debit, 'credit' => (float) $r->credit]]);
    }

    /** Saldo bersih (debit - kredit) per akun s.d. tanggal. */
    public function balancesAsOf(string $asOf): Collection
    {
        return $this->movements(null, $asOf)->map(fn ($m) => round($m['debit'] - $m['credit'], 2));
    }

    public function accountBalance(string $code, string $asOf): float
    {
        $ids = $this->accounts()->filter(fn ($a) => str_starts_with($a->account_code, $code))->keys();
        $bal = $this->balancesAsOf($asOf);

        return round($ids->sum(fn ($id) => $bal[$id] ?? 0), 2);
    }

    // Neraca saldo: posisi saldo per akun di sisi normalnya (debit/kredit).
    public function trialBalance(string $asOf): Collection
    {
        $bal = $this->balancesAsOf($asOf);

        return $this->accounts()->filter(fn ($a) => $a->is_postable && ($a->activate || abs($bal[$a->account_id] ?? 0) > 0.004))
            ->map(function ($a) use ($bal) {
                $net = $bal[$a->account_id] ?? 0;

                return (object) [
                    'account' => $a,
                    'debit' => $net > 0 ? $net : 0,
                    'credit' => $net < 0 ? -$net : 0,
                ];
            })->values();
    }

    public function balanceSheet(string $asOf): array
    {
        $bal = $this->balancesAsOf($asOf);
        $lines = array_fill_keys(['CASH', 'AR', 'INVENTORY', 'OTHER_CA', 'PPE', 'INTANGIBLE', 'AP', 'ACCRUED', 'BANK_LOAN', 'SHARE_CAPITAL', 'RETAINED'], 0.0);
        foreach ($bal as $id => $net) {
            $a = $this->accounts()[$id] ?? null;
            if (! $a) {
                continue;
            }
            $fs = $a->fs_line;
            if (in_array($fs, ['CASH', 'AR', 'INVENTORY', 'OTHER_CA', 'PPE', 'INTANGIBLE'], true)) {
                $lines[$fs] += $net;
            } elseif (in_array($fs, ['AP', 'ACCRUED', 'BANK_LOAN', 'SHARE_CAPITAL'], true)) {
                $lines[$fs] -= $net;
            } else {
                // Laba ditahan + seluruh akun L/R yang belum ditutup ke ekuitas.
                $lines['RETAINED'] -= $net;
            }
        }
        $lines = array_map(fn ($v) => round($v, 2), $lines);
        $currentAssets = $lines['CASH'] + $lines['AR'] + $lines['INVENTORY'] + $lines['OTHER_CA'];
        $nonCurrent = $lines['PPE'] + $lines['INTANGIBLE'];
        $liab = $lines['AP'] + $lines['ACCRUED'] + $lines['BANK_LOAN'];
        $equity = $lines['SHARE_CAPITAL'] + $lines['RETAINED'];

        return $lines + [
            'current_assets' => $currentAssets, 'non_current_assets' => $nonCurrent,
            'total_assets' => round($currentAssets + $nonCurrent, 2),
            'current_liabilities' => $lines['AP'] + $lines['ACCRUED'],
            'total_liabilities' => round($liab, 2), 'total_equity' => round($equity, 2),
            'total_le' => round($liab + $equity, 2),
        ];
    }

    public function incomeStatement(string $from, string $to): array
    {
        $mov = $this->movements($from, $to, ['CLOSING']);
        $lines = array_fill_keys(['REVENUE', 'COGS', 'EMPLOYEE', 'SELLING', 'GA', 'OTHER', 'INCOME_TAX'], 0.0);
        foreach ($mov as $id => $m) {
            $fs = $this->accounts()[$id]->fs_line ?? null;
            if (array_key_exists((string) $fs, $lines)) {
                $lines[$fs] += $m['credit'] - $m['debit']; // + = pendapatan, - = beban
            }
        }
        $lines = array_map(fn ($v) => round($v, 2), $lines);
        $gross = $lines['REVENUE'] + $lines['COGS'];
        $operating = $gross + $lines['EMPLOYEE'] + $lines['SELLING'] + $lines['GA'];
        $pbt = $operating + $lines['OTHER'];

        return $lines + [
            'gross_profit' => round($gross, 2), 'operating_profit' => round($operating, 2),
            'profit_before_tax' => round($pbt, 2), 'net_profit' => round($pbt + $lines['INCOME_TAX'], 2),
        ];
    }

    // Arus kas metode tidak langsung: efek kas tiap akun non-kas = kredit - debit.
    public function cashFlow(string $from, string $to): array
    {
        $mov = $this->movements($from, $to, ['CLOSING']);
        $lines = array_fill_keys(array_keys(self::CF_LABELS), 0.0);
        foreach ($mov as $id => $m) {
            $cf = $this->accounts()[$id]->cf_line ?? 'PROFIT';
            if ($cf === 'CASH') {
                continue;
            }
            $lines[$cf] = ($lines[$cf] ?? 0) + $m['credit'] - $m['debit'];
        }
        $lines = array_map(fn ($v) => round($v, 2), $lines);
        $operating = array_sum(array_intersect_key($lines, array_flip(['PROFIT', 'DA', 'WC_AR', 'WC_INV', 'WC_OTHER_CA', 'WC_AP', 'WC_OTHER_CL', 'TAX'])));
        $opening = $this->accountBalance('111', date('Y-m-d', strtotime($from.' -1 day')));

        return $lines + [
            'operating' => round($operating, 2), 'investing' => $lines['INV_PPE'],
            'financing' => round($lines['FIN_LOAN'] + $lines['FIN_EQUITY'], 2),
            'net_change' => round($operating + $lines['INV_PPE'] + $lines['FIN_LOAN'] + $lines['FIN_EQUITY'], 2),
            'opening_cash' => $opening, 'closing_cash' => $this->accountBalance('111', $to),
        ];
    }

    // Arus kas operasi per bulan (grafik dashboard).
    public function monthlyOperatingCash(int $year, int $upToMonth): array
    {
        $out = [];
        for ($m = 1; $m <= $upToMonth; $m++) {
            $from = sprintf('%04d-%02d-01', $year, $m);
            $out[$m] = $this->cashFlow($from, date('Y-m-t', strtotime($from)))['operating'];
        }

        return $out;
    }
}
