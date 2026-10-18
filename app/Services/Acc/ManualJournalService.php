<?php

/*
 * REMARK
 * Tujuan       : Jurnal umum manual dari layar v2 acc-journal-form: simpan draft (header + baris), ajukan, setujui, kembalikan ke Draft, posting ke GL, balik (reverse), hapus draft; opsi akun dan template.
 * Sumber hulu  : Aturan dipindah apa adanya dari halaman v1 pages::finance.journals (diarsipkan di tmerp_unused/finance_journals_v1_2026-10-18.blade.php); lifecycle dan posting tetap di JournalPostingService.
 * Input        : Isian form (header), baris (account_id, debit, credit, cc_id, narration), user.
 * Output       : tm2acc.fin_voucher + fin_voucher_line berubah (status DRAFT > SUBMITTED > APPROVED > POSTED, Reversed); ValidationException / DomainException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_finance.php (v2.acc.journal.*), layar acc-journal-form, tes ManualJournalServiceTest.
 * Status       : Aktif. Nomor JV-YYYY-MM-NNNN terbit saat Save. Debit harus sama dengan kredit dan tidak nol; akun kontrol AR/AP ditolak; yang sudah Posted tidak pernah diedit.
 */

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinCostCenter;
use App\Models\Acc\FinJournal;
use App\Models\Acc\FinVoucher;
use App\Models\Sys\SecUser;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ManualJournalService
{
    public const ENTRY_KINDS = ['MANUAL' => 'Manual Journal', 'ACCRUAL' => 'Accrual Journal', 'RECURRING' => 'Recurring Journal', 'ADJUSTMENT' => 'Adjustment Journal', 'OPENING' => 'Opening Balance'];

    public const KINDS = self::ENTRY_KINDS + ['REVERSAL' => 'Reversing Journal', 'CLOSING' => 'Year-end Closing'];

    public function __construct(private JournalPostingService $posting) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines  baris form (account_id, debit, credit, cc_id, narration)
     */
    public function save(?FinVoucher $v, array $in, array $lines, SecUser $user): FinVoucher
    {
        $d = Validator::make($in, [
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'journal_kind' => ['required', 'in:'.implode(',', array_keys(self::ENTRY_KINDS))],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'auto_reverse_date' => ['nullable', 'date', 'after:entry_date'],
        ], [], ['entry_date' => 'Transaction date', 'description' => 'Description', 'auto_reverse_date' => 'Auto-reverse date'])->validate();

        $rows = $this->cleanLines($lines);
        Validator::make(['lines' => $rows], ['lines.*.account_id' => ['required', 'exists:tm2acc.fin_account,account_id'], 'lines.*.cc_id' => ['nullable', 'exists:tm2acc.fin_cost_center,cc_id'],
            'lines.*.narration' => ['nullable', 'string', 'max:255']])->validate();

        $debit = round(array_sum(array_column($rows, 'debit')), 2);
        $credit = round(array_sum(array_column($rows, 'credit')), 2);
        if (abs($debit - $credit) > 0.009 || $debit <= 0) {
            throw ValidationException::withMessages(['lines' => 'Debit ('.Locale::number($debit, 2).') must equal credit ('.Locale::number($credit, 2).') and must not be zero.']);
        }
        $recon = FinAccount::whereIn('account_id', array_column($rows, 'account_id'))->where('is_recon', true)->pluck('account_code');
        if ($recon->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Control account '.$recon->implode(', ').' (receivable / payable / advance) is only posted through Order to Cash or Procure to Pay, so the subledger stays in sync.']);
        }

        return DB::connection('tm2acc')->transaction(fn () => $this->posting->saveDraft($v, [
            'voucher_number' => $v?->voucher_number ?? $this->posting->nextJournalNumber($d['entry_date']),
            'journal_id' => FinJournal::where('journal_code', 'GEN')->value('journal_id'),
            'journal_kind' => $d['journal_kind'], 'entry_date' => $d['entry_date'], 'posting_date' => $d['entry_date'],
            'description' => $d['description'], 'reference_no' => ($d['reference_no'] ?? null) ?: null,
            'auto_reverse_date' => $d['journal_kind'] === 'ACCRUAL' ? ($d['auto_reverse_date'] ?? null) ?: null : null,
            'source_type' => 'MANUAL',
        ], $rows, $user->login));
    }

    public function submit(FinVoucher $v, SecUser $user): void
    {
        $this->posting->submit($v, $user->login);
    }

    public function approve(FinVoucher $v, SecUser $user): void
    {
        $this->posting->approve($v, $user);
    }

    public function returnToDraft(FinVoucher $v, SecUser $user): void
    {
        $this->posting->reject($v, $user->login);
    }

    public function post(FinVoucher $v, SecUser $user): void
    {
        $this->posting->postDraft($v, $user->login, true);
    }

    public function reverse(FinVoucher $v, string $date, string $reason, SecUser $user): FinVoucher
    {
        Validator::make(['date' => $date, 'reason' => $reason], ['date' => ['required', 'date'], 'reason' => ['required', 'string', 'max:120']], [], ['date' => 'Reversal date', 'reason' => 'Reason'])->validate();
        abort_if($v->journal_kind === 'REVERSAL', 422, 'A reversing journal cannot be reversed again.');

        return $this->posting->reverse($v, $date, $user->login, $reason);
    }

    public function delete(FinVoucher $v): void
    {
        abort_unless($v->status === 'DRAFT', 422, 'Only a DRAFT can be deleted.');
        DB::connection('tm2acc')->transaction(function () use ($v) {
            $v->lines()->delete();
            $v->delete();
        });
    }

    /** @return array{0: string, 1: ?string} [status panel DevLib, tahap] */
    public function formState(FinVoucher $v): array
    {
        return match ($v->status) {
            'SUBMITTED' => ['approval', 'Pending approval'],
            'APPROVED' => ['approved', 'Approved'],
            'POSTED' => $v->reversed_by_id ? ['closed', 'Reversed'] : ['confirmed', 'Posted'],
            'CANCELLED' => ['cancelled', null],
            default => ['draft', null],
        };
    }

    public function canApprove(FinVoucher $v, SecUser $user): bool
    {
        return $v->status === 'SUBMITTED' && $this->posting->canApprove($user, $this->posting->ruleFor('JOURNAL', $v->totalAmount())?->approver_group);
    }

    public function canReverse(FinVoucher $v): bool
    {
        return $v->status === 'POSTED' && ! $v->reversed_by_id && $v->journal_kind !== 'REVERSAL';
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(FinVoucher $v): array
    {
        $at = fn ($t) => $t?->toDateTimeString();
        $rows = array_filter([
            ['at' => $at($v->created_at), 'by' => $v->created_by, 'action' => 'Created', 'note' => ''],
            $v->submitted_at ? ['at' => $at($v->submitted_at), 'by' => $v->submitted_by, 'action' => 'Submitted', 'note' => ''] : null,
            $v->approved_at ? ['at' => $at($v->approved_at), 'by' => $v->approved_by, 'action' => 'Approved', 'note' => ''] : null,
            $v->posted_at ? ['at' => $at($v->posted_at), 'by' => $v->posted_by, 'action' => 'Posted', 'note' => ''] : null,
            $v->reversed_by_id ? ['at' => $at(FinVoucher::query()->whereKey($v->reversed_by_id)->value('posted_at')), 'by' => FinVoucher::query()->whereKey($v->reversed_by_id)->value('posted_by'),
                'action' => 'Reversed', 'note' => 'Reversing journal '.FinVoucher::query()->whereKey($v->reversed_by_id)->value('voucher_number')] : null,
        ]);

        return array_reverse(array_values($rows));
    }

    /** @return array<int, string> account_id => "code · name": akun jurnal aktif + akun yang sudah dipakai baris jurnal ini */
    public function accountOptions(?FinVoucher $v = null): array
    {
        $used = $v ? $v->lines->pluck('account_id')->all() : [];

        return FinAccount::query()->where(fn ($q) => $q->where('is_postable', true)->where('activate', true)->orWhereIn('account_id', $used))
            ->orderBy('account_code')->get(['account_id', 'account_code', 'account_name'])
            ->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    /** @return array<int, string> cc_id => nama cost center aktif */
    public function costCenterOptions(): array
    {
        return FinCostCenter::query()->where('activate', true)->orderBy('cc_code')->pluck('cc_name', 'cc_id')->all();
    }

    /** Jurnal Recurring yang sudah diposting, satu per deskripsi (template). */
    public function templates(): Collection
    {
        return FinVoucher::with('lines')->where('journal_kind', 'RECURRING')->where('status', 'POSTED')->orderByDesc('entry_date')->limit(20)->get()->unique('description')->values();
    }

    /** @return array<int, array{account_id:int, debit:float, credit:float, cc_id:?int, narration:?string}> */
    private function cleanLines(array $lines): array
    {
        $num = fn ($v) => (float) (Locale::input($v) ?? 0);
        $rows = [];
        foreach ($lines as $l) {
            $l = (array) $l;
            $row = ['account_id' => (int) ($l['account_id'] ?? 0), 'debit' => $num($l['debit'] ?? 0), 'credit' => $num($l['credit'] ?? 0),
                'cc_id' => ((int) ($l['cc_id'] ?? 0)) ?: null, 'narration' => trim((string) ($l['narration'] ?? '')) ?: null];
            if (! $row['account_id'] && ! $row['debit'] && ! $row['credit']) {
                continue;
            }
            if (! $row['account_id']) {
                throw ValidationException::withMessages(['lines' => 'Every line with an amount needs an account.']);
            }
            if ($row['debit'] > 0 && $row['credit'] > 0) {
                throw ValidationException::withMessages(['lines' => 'One line cannot contain both a debit and a credit.']);
            }
            if ($row['debit'] < 0 || $row['credit'] < 0) {
                throw ValidationException::withMessages(['lines' => 'Debit and credit cannot be negative.']);
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
