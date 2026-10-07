<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinBankRecon;
use App\Models\Acc\FinBankStatement;
use App\Models\Acc\FinVoucherLine;
use App\Services\Acc\BankReconService;
use App\Services\Acc\LedgerService;
use Devlib\Locale;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

// Finance > Transaction Flows > Bank & Reconciliation (v2): Import Statement -> Normalize -> Auto-Match -> Investigate (cocokkan manual / jurnal penyesuaian DRAFT yang
// wajib disetujui di Journal Entries) -> Reconcile. Aturan di BankReconService (sama dengan v1); voucher kas / bank tetap dibuat di Cashier. Kesalahan bisnis service
// (abort 422 / DomainException) dijadikan ValidationException supaya layar menampilkan pesannya, bukan halaman galat.
final class BankDesk
{
    public const TONES = ['MATCHED' => 'success', 'ADJUSTED' => 'info', 'IGNORED' => 'neutral', 'UNMATCHED' => 'warn'];

    private const LIMIT = 150;

    public static function service(): BankReconService
    {
        return app(BankReconService::class);
    }

    /** @return array<int, string> rekening kas / bank (kode 111*) postable */
    public static function accountOptions(): array
    {
        return FinAccount::where('is_postable', true)->where('activate', true)->where('account_code', 'like', '111%')->orderBy('account_code')->get()
            ->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    public static function defaultAccount(): ?int
    {
        return FinAccount::where('account_code', '111200')->value('account_id') ?: array_key_first(self::accountOptions());
    }

    /** @return array<int, string> semua akun postable aktif (lawan jurnal penyesuaian) */
    public static function counterAccounts(): array
    {
        return FinAccount::where('is_postable', true)->where('activate', true)->orderBy('account_code')->get()
            ->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    /** @return array{stmt: ?float, book: float, diff: ?float, unmatched_n: int, unmatched_sum: float, last: ?FinBankRecon} */
    public static function stats(int $accountId): array
    {
        $account = FinAccount::find($accountId);
        $unmatched = FinBankStatement::where('account_id', $accountId)->where('match_status', 'UNMATCHED')->get();
        $lastDate = FinBankStatement::where('account_id', $accountId)->max('txn_date');
        $book = $account ? app(LedgerService::class)->accountBalance($account->account_code, $lastDate ?: now()->toDateString()) : 0.0;
        $stmt = self::service()->statementBalance($accountId);

        return ['stmt' => $stmt, 'book' => $book, 'diff' => $stmt === null ? null : round($stmt - $book, 2), 'unmatched_n' => $unmatched->count(),
            'unmatched_sum' => (float) $unmatched->sum(fn ($s) => (float) $s->amount_in + (float) $s->amount_out), 'last' => FinBankRecon::where('account_id', $accountId)->orderByDesc('recon_id')->first()];
    }

    /** @return list<array<string, mixed>> kartu KPI (props x-dl::kpi) */
    public static function kpis(array $s, ?FinAccount $account): array
    {
        $agrees = $s['diff'] !== null && abs($s['diff']) < 0.01;
        $kpi = fn (string $label, float|int|string $value, string $format, string $icon, string $tone, ?string $note) => ['label' => $label, 'value' => $value, 'format' => $format,
            'previous' => null, 'goodWhen' => 'up', 'icon' => $icon, 'tone' => $tone, 'note' => $note];

        return [
            $kpi('Statement Balance', $s['stmt'] ?? 0, 'money', 'fa-building-columns', 'accent', $s['stmt'] === null ? t('No statement imported') : ($account?->account_name)),
            $kpi('GL Book Balance', $s['book'], 'money', 'fa-book', $agrees ? 'success' : 'warn',
                $s['diff'] === null ? t('No statement imported') : ($agrees ? t('Agrees with statement') : tf('Difference :amount', ['amount' => Locale::money($s['diff'])]))),
            $kpi('Unmatched Items', $s['unmatched_n'], 'number', 'fa-hourglass-half', $s['unmatched_n'] ? 'warn' : 'success', tf(':amount total', ['amount' => Locale::money($s['unmatched_sum'])])),
            $kpi('Last Reconciled', $s['last'] ? Locale::d($s['last']->statement_date) : '—', 'text', 'fa-circle-check', $s['last']?->status === 'RECONCILED' ? 'success' : 'warn',
                $s['last'] ? ($s['last']->status === 'RECONCILED' ? t('Completed') : t('With difference')) : t('Never')),
        ];
    }

    /** @return list<array<string, mixed>> langkah x-dl::flow (alur rekonsiliasi bank) */
    public static function flow(array $s): array
    {
        $step = fn (string $key, string $label, string $icon, string $note, ?string $badge = null) => ['key' => $key, 'label' => t($label), 'icon' => $icon, 'status' => $badge ? 'review' : 'todo', 'note' => t($note).($badge ? ' · '.$badge : '')];

        return [
            $step('import', 'Import Statement', 'fa-file-import', 'CSV (BCA/Mandiri export), or manual bank journal entry.'),
            $step('normalize', 'Normalize', 'fa-wand-magic-sparkles', 'Bank date, value date, amount, reference, counterparty.'),
            $step('match', 'Auto-Match', 'fa-bolt', 'Match receipts, payments, transfers using amount and reference rules.', $s['unmatched_n'] ? tf(':n unmatched', ['n' => $s['unmatched_n']]) : null),
            $step('investigate', 'Investigate', 'fa-magnifying-glass', 'Create bank charge, interest, timing, or suspense journal only with approval.'),
            $step('reconcile', 'Reconcile', 'fa-circle-check', 'Certified statement balance equals reconciled book balance.'),
        ];
    }

    /** @return Collection<int, FinVoucherLine> mutasi buku (voucher POSTED) akun ini, terbaru dulu, maksimal 150 */
    public static function movements(int $accountId, string $type = '', string $search = ''): Collection
    {
        return self::service()->bookMovements($accountId)
            ->when($type === 'IN', fn ($c) => $c->filter(fn ($l) => (float) $l->debit > 0))
            ->when($type === 'OUT', fn ($c) => $c->filter(fn ($l) => (float) $l->credit > 0))
            ->when($search !== '', fn ($c) => $c->filter(fn ($l) => str_contains(strtolower($l->voucher->voucher_number.' '.$l->voucher->description.' '.$l->voucher->reference_no), strtolower($search))))
            ->take(self::LIMIT)->values();
    }

    /** @return array<int, string> id voucher yang sudah dipasangkan => status */
    public static function matchedVouchers(): array
    {
        return FinBankStatement::whereNotNull('matched_voucher_id')->pluck('match_status', 'matched_voucher_id')->all();
    }

    /** @return Collection<int, FinBankStatement> */
    public static function statement(int $accountId): Collection
    {
        return FinBankStatement::where('account_id', $accountId)->orderByDesc('txn_date')->orderByDesc('stmt_line_id')->limit(self::LIMIT)->get();
    }

    public static function line(int $id): ?FinBankStatement
    {
        return FinBankStatement::find($id);
    }

    /** @return Collection<int, FinVoucherLine> calon pasangan buku untuk satu mutasi (±30 hari, nominal terdekat dulu, belum terpakai) */
    public static function candidates(FinBankStatement $s): Collection
    {
        $isIn = (float) $s->amount_in > 0;
        $used = array_keys(self::matchedVouchers());
        $amount = fn ($l) => $isIn ? (float) $l->debit : (float) $l->credit;

        return self::service()->bookMovements((int) $s->account_id, $s->txn_date->copy()->subDays(30)->toDateString(), $s->txn_date->copy()->addDays(30)->toDateString())
            ->filter(fn ($l) => $amount($l) > 0 && ! in_array($l->voucher_id, $used))
            ->sortBy(fn ($l) => abs($amount($l) - ($isIn ? (float) $s->amount_in : (float) $s->amount_out)))->take(15)->values();
    }

    // ---------- aksi ----------

    /** Jalankan aksi service; galat bisnis menjadi ValidationException (pesan ramah). */
    private static function run(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['bank' => $e->getMessage() ?: t('The process failed.')]);
        }
    }

    public static function import(int $accountId, ?UploadedFile $file, string $by): int
    {
        Validator::make(['csv' => $file], ['csv' => ['required', 'file', 'mimes:csv,txt', 'max:4096']], [], ['csv' => t('CSV file')])->validate();

        return self::run(fn () => self::service()->importCsv($accountId, $file->getRealPath(), $by));
    }

    public static function autoMatch(int $accountId, string $by): int
    {
        return self::run(fn () => self::service()->autoMatch($accountId, $by));
    }

    public static function manualMatch(int $stmtLineId, int $voucherId, string $by): void
    {
        self::run(fn () => self::service()->match(FinBankStatement::findOrFail($stmtLineId), $voucherId, $by));
    }

    /** @return string nomor jurnal penyesuaian (status DRAFT) */
    public static function adjust(int $stmtLineId, int $counterAccountId, string $by): string
    {
        Validator::make(['account' => $counterAccountId], ['account' => ['required', 'exists:tm2acc.fin_account,account_id']], [], ['account' => t('Account')])->validate();

        return self::run(fn () => (string) self::service()->createAdjustment(FinBankStatement::findOrFail($stmtLineId), $counterAccountId, $by)->voucher_number);
    }

    public static function ignore(int $stmtLineId, string $by): void
    {
        self::run(fn () => FinBankStatement::findOrFail($stmtLineId)->update(['match_status' => 'IGNORED', 'updated_by' => $by]));
    }

    /** @return array{ok: bool, message: string} */
    public static function reconcile(int $accountId, string $by): array
    {
        $r = self::run(fn () => self::service()->reconcile($accountId, $by));

        return $r->status === 'RECONCILED'
            ? ['ok' => true, 'message' => tf('Reconciliation done: statement balance = book balance as at :date.', ['date' => Locale::d($r->statement_date)])]
            : ['ok' => false, 'message' => tf('Reconciliation recorded WITH a difference of :amount and :n unmatched lines.', ['amount' => Locale::money((float) $r->difference), 'n' => $r->unmatched_count])];
    }
}
