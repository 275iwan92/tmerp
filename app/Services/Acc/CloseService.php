<?php

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinBankRecon;
use App\Models\Acc\FinCloseTask;
use App\Models\Acc\FinJournal;
use App\Models\Acc\FinPeriod;
use App\Models\Acc\FinTaxRegister;
use App\Models\Acc\FinVoucher;

// Month-End Close Flow (blueprint): cut-off -> reconcile -> run schedules ->
// review TB -> approve & lock. Lock = FinPeriodGuard::changeStatus(CLOSED),
// ireversibel (financeaccounting_delta_period_close.md).
class CloseService
{
    public const TASKS = [
        ['BANK_RECON', 'Bank reconciliations', 'All primary bank accounts reconciled; variance logged'],
        ['AR_REVIEW', 'Accounts receivable review', 'Aging reviewed and subledger agrees with control account'],
        ['AP_CUTOFF', 'Accounts payable cut-off', 'Vendor bills and accruals captured'],
        ['DEPR_AMORT', 'Depreciation and amortization', 'Scheduled entries posted to GL'],
        ['TAX_REVIEW', 'Tax review and posting', 'PPN and PPh reconciliation'],
        ['MGMT_APPROVAL', 'Management review & approval', 'Controller sign-off before period lock'],
    ];

    public function __construct(private FinPeriodGuard $guard, private LedgerService $ledger, private JournalPostingService $journal) {}

    // Periode tutup buku berjalan = bulan lewat tertua yang belum CLOSED DAN
    // sudah ada transaksinya. Tanpa syarat "ada transaksi", tahun-tahun lama
    // yang periodenya digenerate tapi kosong akan menyangkut di sini selamanya.
    public function currentPeriod(): ?FinPeriod
    {
        $past = FinPeriod::withoutGlobalScopes()->where('is_adjustment', false)->where('status', '<>', 'CLOSED')
            ->where('end_date', '<', now()->toDateString())->orderBy('start_date')->get();

        foreach ($past as $p) {
            $hasEntries = FinVoucher::withoutGlobalScopes()
                ->whereBetween('entry_date', [$p->start_date->toDateString(), $p->end_date->toDateString()])->exists();
            if ($hasEntries) {
                return $p;
            }
        }

        return $past->last();
    }

    public function lastClosed(): ?FinPeriod
    {
        return FinPeriod::withoutGlobalScopes()->where('is_adjustment', false)->where('status', 'CLOSED')->orderByDesc('start_date')->first();
    }

    public function start(FinPeriod $period, string $user, string $origin = 'LIVE'): void
    {
        foreach (self::TASKS as $i => [$code, $title, $desc]) {
            FinCloseTask::withoutGlobalScopes()->firstOrCreate(['period_id' => $period->period_id, 'task_code' => $code], [
                'step_no' => $i + 1, 'title' => $title, 'description' => $desc, 'status' => 'PENDING',
                'data_origin' => $origin, 'created_by' => $user,
            ]);
        }
    }

    public function setTask(FinCloseTask $task, string $status, string $user, ?string $note = null): void
    {
        $task->update([
            'status' => $status, 'evidence_note' => $note ?? $task->evidence_note,
            'completed_by' => $status === 'COMPLETE' ? $user : null,
            'completed_at' => $status === 'COMPLETE' ? now() : null, 'updated_by' => $user,
        ]);
    }

    /** Kontrol otomatis (panel "Close Controls"): [judul, keterangan, status Passed|Exception|Review]. */
    public function controls(FinPeriod $period): array
    {
        $end = $period->end_date->toDateString();
        $tb = $this->ledger->trialBalance($end);
        $diff = round($tb->sum('debit') - $tb->sum('credit'), 2);
        $drafts = FinVoucher::whereIn('status', ['DRAFT', 'SUBMITTED', 'APPROVED'])->whereBetween('entry_date', [$period->start_date->toDateString(), $end])->count();
        $bankIds = FinAccount::where('account_code', 'like', '111%')->where('is_postable', true)->pluck('account_id');
        $recon = FinBankRecon::whereIn('account_id', $bankIds)->where('statement_date', '>=', $period->start_date)->orderByDesc('recon_id')->first();
        $ppn = FinTaxRegister::where('tax_code', 'PPN_OUT')->where('fiscal_year', $period->fiscal_year)->where('period_no', $period->period_no)->first();

        return [
            ['▤', t('Trial balance validation'), abs($diff) < 0.01 ? t('Total debits equal total credits') : tf('Out of balance IDR :amount', ['amount' => number_format($diff, 0, ',', '.')]), abs($diff) < 0.01 ? 'Passed' : 'Exception'],
            ['▥', t('Bank reconciliation'), $recon ? ($recon->status === 'RECONCILED' ? t('Statement agrees with book balance') : tf('Difference IDR :amount', ['amount' => number_format((float) $recon->difference, 0, ',', '.')])) : t('No reconciliation recorded for this period'), $recon?->status === 'RECONCILED' ? 'Passed' : 'Review'],
            ['!', t('Unposted journals'), $drafts ? tf(':n journals not yet posted in :period', ['n' => $drafts, 'period' => $period->period_name]) : t('No draft or pending journals'), $drafts ? 'Exception' : 'Passed'],
            ['%', t('Tax account reconciliation'), $ppn ? tf('PPN register status: :status', ['status' => $ppn->status]) : t('PPN workpaper not generated'), $ppn && in_array($ppn->status, ['PAID', 'FILED'], true) ? 'Passed' : 'Review'],
        ];
    }

    public function lock(FinPeriod $period, string $reason, string $user): void
    {
        $this->guard->changeStatus($period, 'CLOSED', $reason, $user);
    }

    // §7: voucher DRAFT penutup tahun -- saldo L/R dinolkan ke 322000, lalu
    // 322000 dipindah ke 321000. Direview & diposting manual (tidak auto-post).
    public function yearEndVoucher(int $year, string $user): FinVoucher
    {
        $mov = $this->ledger->movements("{$year}-01-01", "{$year}-12-31", ['CLOSING']);
        $pl = ['REVENUE', 'COGS', 'EXPENSE', 'OTHER_INCOME', 'OTHER_EXPENSE'];
        $lines = [];
        $net = 0.0;
        foreach ($mov as $id => $m) {
            $a = $this->ledger->accounts()[$id] ?? null;
            if (! $a || ! in_array($a->account_type, $pl, true)) {
                continue;
            }
            $bal = round($m['debit'] - $m['credit'], 2);
            if (abs($bal) < 0.005) {
                continue;
            }
            $lines[] = ['account_id' => $id, $bal > 0 ? 'credit' : 'debit' => abs($bal), 'narration' => 'Tutup '.$a->account_code];
            $net += $bal;
        }
        abort_if(empty($lines), 422, tf('There is no profit and loss balance to close in :year.', ['year' => $year]));
        $current = (int) FinAccount::where('account_code', '322000')->value('account_id');
        $retained = (int) FinAccount::where('account_code', '321000')->value('account_id');
        $profit = round(-$net, 2);
        // Laba ditutup ke 322000 lalu langsung dipindah ke 321000 (dua baris, efek bersih ke Laba Ditahan).
        $lines[] = ['account_id' => $current, $profit >= 0 ? 'credit' : 'debit' => abs($profit), 'narration' => 'Laba tahun berjalan '.$year];
        $lines[] = ['account_id' => $current, $profit >= 0 ? 'debit' : 'credit' => abs($profit), 'narration' => 'Pindah ke laba ditahan'];
        $lines[] = ['account_id' => $retained, $profit >= 0 ? 'credit' : 'debit' => abs($profit), 'narration' => 'Laba ditahan '.$year];

        $date = "{$year}-12-31";

        return $this->journal->saveDraft(null, [
            'voucher_number' => $this->journal->nextJournalNumber($date),
            'journal_id' => FinJournal::where('journal_code', 'GEN')->value('journal_id'),
            'journal_kind' => 'CLOSING', 'entry_date' => $date, 'source_type' => 'YEAR_END', 'source_id' => $year,
            'description' => "Jurnal penutup tahun {$year}",
        ], $lines, $user);
    }
}
