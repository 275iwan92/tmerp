<?php

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAsset;
use App\Models\Acc\FinBankRecon;
use App\Models\Acc\FinBankStatement;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinPaymentAlloc;
use App\Models\Acc\FinTaxRegister;
use App\Models\Acc\FinVoucher;
use App\Support\Finance\Fmt;
use Carbon\Carbon;

// Angka ringkas utk Process Engine & Dashboard (mockup blueprint). Semuanya
// dihitung dari data nyata; kalau belum ada datanya hasilnya 0 -- tidak ada
// nilai contoh yang dikarang.
class FinanceMetrics
{
    public function __construct(private LedgerService $ledger, private CloseService $close, private AssetService $assets) {}

    public function headline(?string $asOf = null): array
    {
        $asOf = $asOf ?: now()->toDateString();
        $ytdFrom = date('Y-01-01', strtotime($asOf));
        $is = $this->ledger->incomeStatement($ytdFrom, $asOf);
        $prevYear = (int) date('Y', strtotime($asOf)) - 1;
        $isPrev = $this->ledger->incomeStatement("{$prevYear}-01-01", date('Y-m-d', strtotime($asOf.' -1 year')));

        $ar = FinInvoice::where('invoice_type', 'CUSTOMER_INVOICE')->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID')->get();
        $ap = FinInvoice::where('invoice_type', 'SUPPLIER_BILL')->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID')->get();
        $cashNow = $this->ledger->accountBalance('111', $asOf);
        $cashPrev = $this->ledger->accountBalance('111', date('Y-m-t', strtotime(date('Y-m-01', strtotime($asOf)).' -1 day')));

        return [
            'cash' => $cashNow,
            'cash_change' => $cashPrev != 0 ? round(($cashNow - $cashPrev) / abs($cashPrev) * 100, 1) : null,
            'ar' => round($ar->sum(fn ($i) => $i->outstanding()), 2), 'ar_n' => $ar->count(),
            'ar_overdue' => round($ar->filter(fn ($i) => $i->due_date->lt(today()))->sum(fn ($i) => $i->outstanding()), 2),
            'ar_overdue_n' => $ar->filter(fn ($i) => $i->due_date->lt(today()))->count(),
            'ap' => round($ap->sum(fn ($i) => $i->outstanding()), 2), 'ap_n' => $ap->count(),
            'ap_due' => round($ap->filter(fn ($i) => $i->due_date->lte(today()->addDays(7)))->sum(fn ($i) => $i->outstanding()), 2),
            'ap_due_n' => $ap->filter(fn ($i) => $i->due_date->lte(today()->addDays(7)))->count(),
            'revenue' => $is['REVENUE'], 'net_profit' => $is['net_profit'],
            'profit_change' => ($isPrev['net_profit'] ?? 0) != 0 ? round(($is['net_profit'] - $isPrev['net_profit']) / abs($isPrev['net_profit']) * 100, 1) : null,
            'collections_mtd' => round((float) FinPaymentAlloc::whereBetween('alloc_date', [date('Y-m-01', strtotime($asOf)), $asOf])
                ->whereIn('invoice_id', FinInvoice::where('invoice_type', 'CUSTOMER_INVOICE')->select('invoice_id'))->sum('amount'), 2),
        ];
    }

    /** Volume & kendali proses bulan berjalan (kartu Process Engine). */
    public function engine(?string $month = null): array
    {
        $month = $month ?: now()->format('Y-m');
        $from = $month.'-01';
        $to = date('Y-m-t', strtotime($from));

        $pending = FinVoucher::whereIn('status', ['SUBMITTED', 'APPROVED'])->with('lines')->get();
        $period = $this->close->currentPeriod();
        $tasks = $period ? $period->tasks : collect();
        $controls = $period ? collect($this->close->controls($period)) : collect();

        return [
            'source_docs' => FinInvoice::whereBetween('invoice_date', [$from, $to])->count()
                + FinVoucher::whereBetween('entry_date', [$from, $to])->count()
                + FinBankStatement::whereBetween('txn_date', [$from, $to])->count(),
            'pending_n' => $pending->count(),
            'pending_value' => round($pending->sum(fn ($v) => $v->totalAmount()), 2),
            'posted_n' => FinVoucher::where('status', 'POSTED')->whereBetween('entry_date', [$from, $to])->count(),
            'tb_balanced' => abs(round($this->ledger->trialBalance($to)->sum('debit') - $this->ledger->trialBalance($to)->sum('credit'), 2)) < 0.01,
            'close_period' => $period,
            'close_pct' => $tasks->count() ? (int) round($tasks->where('status', 'COMPLETE')->count() / $tasks->count() * 100) : 0,
            'close_exceptions' => $controls->filter(fn ($c) => $c[3] === 'Exception')->count(),
        ];
    }

    /** Kontrol yang butuh perhatian (panel "Controls Requiring Attention"). */
    public function attention(): array
    {
        $unapproved = FinVoucher::whereIn('status', ['DRAFT', 'SUBMITTED'])->with('lines')->get();
        $bankIds = FinAccount::where('account_code', 'like', '111%')->where('is_postable', true)->pluck('account_id');
        $unmatched = FinBankStatement::whereIn('account_id', $bankIds)->where('match_status', 'UNMATCHED')->get();
        $recon = FinBankRecon::whereIn('account_id', $bankIds)->orderByDesc('recon_id')->first();
        $inputVat = FinInvoice::where('invoice_type', 'SUPPLIER_BILL')->where('doc_status', 'POSTED')->where('tax_amount', '>', 0)->where('tax_validated', false)->count();
        $schedules = $this->assets->preview((int) now()->format('Y'), (int) now()->format('m'));
        $taxDue = FinTaxRegister::whereNotIn('status', ['FILED'])->whereNotNull('payment_due')->where('payment_due', '<=', today()->addDays(14))->count();

        return [
            ['!', 're', t('Unapproved journals'), tf(':n journals waiting for review or approval', ['n' => $unapproved->count()]), $unapproved->count() ? Fmt::short($unapproved->sum(fn ($v) => $v->totalAmount())) : '0'],
            ['▥', 'or', t('Bank reconciliation difference'), tf(':n unmatched movements', ['n' => $unmatched->count()]).($recon ? ' · '.tf('last diff :amount', ['amount' => Fmt::rp($recon->difference)]) : ''), (string) $unmatched->count()],
            ['%', 'or', t('Input VAT validation'), $inputVat ? tf(':n supplier tax invoices need review', ['n' => $inputVat]) : t('All supplier tax invoices validated'), (string) $inputVat],
            ['◔', 'bl', t('Scheduled entries'), tf(':n depreciation/amortization schedules due this month', ['n' => $schedules->count()]), (string) $schedules->count()],
            ['◷', 'or', t('Tax obligations due'), tf(':n obligations due within 14 days', ['n' => $taxDue]), (string) $taxDue],
        ];
    }

    /** Tugas prioritas (panel "Finance Action Center"). */
    public function actions(): array
    {
        $overdueAr = FinInvoice::where('invoice_type', 'CUSTOMER_INVOICE')->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID')->where('due_date', '<', today())->count();
        $dueAp = FinInvoice::where('invoice_type', 'SUPPLIER_BILL')->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID')->where('due_date', '<=', today()->addDays(7))->count();
        $period = $this->close->currentPeriod();
        $tasks = $period ? $period->tasks : collect();
        $draftAssets = FinAsset::where('status', 'DRAFT')->count();

        return [
            ['!', 're', t('Overdue customer invoices'), t('Follow-up required'), (string) $overdueAr],
            ['◷', 'or', t('Vendor bills due this week'), t('Payment run required'), (string) $dueAp],
            ['✓', 'bl', tf(':period close', ['period' => $period?->period_name ?? t('Period')]), $tasks->count() ? tf(':done of :total controls complete', ['done' => $tasks->where('status', 'COMPLETE')->count(), 'total' => $tasks->count()]) : t('Checklist not started'), $tasks->count() ? (int) round($tasks->where('status', 'COMPLETE')->count() / $tasks->count() * 100).'%' : '0%'],
            ['▰', 'pu', t('Assets awaiting capitalization'), t('Review and capitalize'), (string) $draftAssets],
        ];
    }

    /** Tenggat pajak & tutup buku (panel "Compliance Center"). */
    public function compliance(): array
    {
        $out = [];
        foreach (FinTaxRegister::whereNotIn('status', ['FILED'])->whereNotNull('payment_due')->orderBy('payment_due')->limit(3)->get() as $r) {
            $days = (int) today()->diffInDays($r->payment_due, false);
            $out[] = [$days < 0 ? '!' : '%', $days < 0 ? 're' : 'or', $r->description, $days < 0 ? tf('Late :n days', ['n' => abs($days)]) : tf('Pay :date', ['date' => Fmt::date($r->payment_due)]), $days < 0 ? abs($days).'d late' : $days.'d'];
        }
        if ($period = $this->close->currentPeriod()) {
            $tasks = $period->tasks;
            $out[] = ['✓', 'bl', tf(':period period close', ['period' => $period->period_name]), $tasks->count() ? tf(':done of :total closing tasks completed', ['done' => $tasks->where('status', 'COMPLETE')->count(), 'total' => $tasks->count()]) : t('Checklist not created yet'),
                ($tasks->count() ? (int) round($tasks->where('status', 'COMPLETE')->count() / $tasks->count() * 100) : 0).'%'];
        }
        $sched = $this->assets->preview((int) now()->format('Y'), (int) now()->format('m'));
        $out[] = ['◔', 'or', tf(':month depreciation & amortization', ['month' => now()->format('F')]), $sched->count() ? t('Scheduled for period close') : t('Already posted'), (string) $sched->count()];

        return $out;
    }

    /** Tren arus kas operasi 12 bulan terakhir utk grafik dashboard. */
    public function cashTrend(): array
    {
        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = Carbon::now()->startOfMonth()->subMonths($i);
            $out[$m->format('M')] = $this->ledger->cashFlow($m->toDateString(), $m->copy()->endOfMonth()->toDateString())['operating'];
        }

        return $out;
    }
}
