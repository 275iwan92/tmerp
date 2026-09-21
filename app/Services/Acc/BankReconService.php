<?php

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinBankRecon;
use App\Models\Acc\FinBankStatement;
use App\Models\Acc\FinJournal;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Bank Reconciliation Flow (blueprint): Import -> Normalize -> Auto-Match ->
// Investigate (jurnal biaya/bunga hanya dgn approval) -> Reconcile
// (saldo rekening koran = saldo buku yang direkonsiliasi).
class BankReconService
{
    public function __construct(private JournalPostingService $journal, private LedgerService $ledger) {}

    /**
     * Import CSV mutasi. Kolom (header wajib, urutan bebas):
     * date, description, reference, counterparty, debit|amount_out, credit|amount_in, balance
     * Tanggal dd/mm/yyyy atau yyyy-mm-dd; angka boleh pakai pemisah ribuan.
     */
    public function importCsv(int $accountId, string $path, string $user, string $origin = 'LIVE'): int
    {
        $fh = fopen($path, 'r');
        $first = fgets($fh);
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $head = array_map(fn ($h) => strtolower(trim($h, " \t\n\r\0\x0B\xEF\xBB\xBF\"")), str_getcsv($first, $sep));
        $col = fn (array $names) => collect($names)->map(fn ($n) => array_search($n, $head, true))->first(fn ($i) => $i !== false);
        $map = [
            'date' => $col(['date', 'tanggal', 'txn_date', 'tgl']),
            'desc' => $col(['description', 'keterangan', 'uraian']),
            'ref' => $col(['reference', 'referensi', 'ref', 'no']),
            'party' => $col(['counterparty', 'pihak', 'nama']),
            'in' => $col(['credit', 'kredit', 'amount_in', 'masuk', 'cr']),
            'out' => $col(['debit', 'debet', 'amount_out', 'keluar', 'db']),
            'bal' => $col(['balance', 'saldo', 'running_balance']),
        ];
        abort_if($map['date'] === null || ($map['in'] === null && $map['out'] === null), 422, 'Header CSV wajib memuat kolom tanggal dan kredit/debit.');

        $batch = 'IMP-'.now()->format('YmdHis');
        $num = function ($v): float {
            $v = trim(str_replace(['Rp', ' '], '', (string) $v));
            if (preg_match('/,\d{1,2}$/', $v)) {           // 1.234.567,50
                return (float) str_replace(['.', ','], ['', '.'], $v);
            }
            if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v)) { // 1.234.567
                return (float) str_replace('.', '', $v);
            }

            return (float) str_replace(',', '', $v);        // 1,234,567.50
        };
        $n = 0;
        DB::connection('tm2acc')->transaction(function () use ($fh, $sep, $map, $num, $accountId, $batch, $user, $origin, &$n) {
            while (($row = fgetcsv($fh, 0, $sep)) !== false) {
                if (! isset($row[$map['date']]) || trim($row[$map['date']]) === '') {
                    continue;
                }
                $raw = trim($row[$map['date']]);
                $date = preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $raw, $m) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : date('Y-m-d', strtotime($raw));
                FinBankStatement::create([
                    'account_id' => $accountId, 'txn_date' => $date, 'value_date' => $date,
                    'reference' => $map['ref'] !== null ? trim($row[$map['ref']] ?? '') : null,
                    'description' => $map['desc'] !== null ? trim($row[$map['desc']] ?? '') : null,
                    'counterparty' => $map['party'] !== null ? trim($row[$map['party']] ?? '') : null,
                    'amount_in' => $map['in'] !== null ? abs($num($row[$map['in']] ?? 0)) : 0,
                    'amount_out' => $map['out'] !== null ? abs($num($row[$map['out']] ?? 0)) : 0,
                    'running_balance' => $map['bal'] !== null && ($row[$map['bal']] ?? '') !== '' ? $num($row[$map['bal']]) : null,
                    'import_batch' => $batch, 'data_origin' => $origin, 'created_by' => $user,
                ]);
                $n++;
            }
        });
        fclose($fh);

        return $n;
    }

    /** Mutasi buku (voucher POSTED) pada akun bank ini. */
    public function bookMovements(int $accountId, ?string $from = null, ?string $to = null): Collection
    {
        return FinVoucherLine::with('voucher')->where('account_id', $accountId)
            ->whereHas('voucher', fn ($q) => $q->where('status', 'POSTED')->where('stat_cancel', 0)
                ->when($from, fn ($w) => $w->where('entry_date', '>=', $from))
                ->when($to, fn ($w) => $w->where('entry_date', '<=', $to)))
            ->get()->sortByDesc(fn ($l) => $l->voucher->entry_date->format('Ymd').str_pad((string) $l->line_id, 10, '0', STR_PAD_LEFT))->values();
    }

    // Auto-match: nominal sama persis & arah sama, referensi mirip atau tanggal
    // selisih <= 3 hari. Satu voucher hanya boleh dipasangkan ke satu mutasi.
    public function autoMatch(int $accountId, string $user): int
    {
        $used = FinBankStatement::withoutGlobalScopes()->whereNotNull('matched_voucher_id')->pluck('matched_voucher_id')->flip();
        $book = $this->bookMovements($accountId)->reject(fn ($l) => isset($used[$l->voucher_id]));
        $n = 0;
        foreach (FinBankStatement::where('account_id', $accountId)->where('match_status', 'UNMATCHED')->orderBy('txn_date')->get() as $s) {
            $isIn = (float) $s->amount_in > 0;
            $amount = $isIn ? (float) $s->amount_in : (float) $s->amount_out;
            $hit = $book->first(function ($l) use ($isIn, $amount, $s) {
                $bookAmt = $isIn ? (float) $l->debit : (float) $l->credit;
                if (abs($bookAmt - $amount) > 0.01) {
                    return false;
                }
                $refOk = $s->reference && (str_contains((string) $l->voucher->reference_no, $s->reference) || str_contains((string) $l->voucher->voucher_number, $s->reference));
                $days = abs($l->voucher->entry_date->diffInDays($s->txn_date, false));

                return $refOk || $days <= 3;
            });
            if ($hit) {
                $s->update(['match_status' => 'MATCHED', 'matched_voucher_id' => $hit->voucher_id, 'updated_by' => $user]);
                $book = $book->reject(fn ($l) => $l->voucher_id === $hit->voucher_id);
                $n++;
            }
        }

        return $n;
    }

    public function match(FinBankStatement $line, int $voucherId, string $user): void
    {
        abort_if(FinBankStatement::withoutGlobalScopes()->where('matched_voucher_id', $voucherId)->where('stmt_line_id', '<>', $line->stmt_line_id)->exists(), 422, 'Voucher ini sudah dipasangkan ke mutasi lain.');
        $line->update(['match_status' => 'MATCHED', 'matched_voucher_id' => $voucherId, 'updated_by' => $user]);
    }

    // Mutasi tanpa pasangan buku (biaya admin, bunga, dsb): buat jurnal bank
    // DRAFT -> lewat approval Journal Entries (blueprint: "only with approval").
    public function createAdjustment(FinBankStatement $line, int $counterAccountId, string $user): FinVoucher
    {
        $isIn = (float) $line->amount_in > 0;
        $amount = $isIn ? (float) $line->amount_in : (float) $line->amount_out;
        $date = $line->txn_date->toDateString();
        $voucher = $this->journal->saveDraft(null, [
            'voucher_number' => $this->journal->nextJournalNumber($date),
            'journal_id' => FinJournal::where('journal_code', 'BNK')->value('journal_id'),
            'journal_kind' => 'ADJUSTMENT', 'entry_date' => $date, 'source_type' => 'BANK_STATEMENT', 'source_id' => $line->stmt_line_id,
            'reference_no' => $line->reference, 'partner_name' => $line->counterparty,
            'description' => 'Penyesuaian bank: '.$line->description, 'data_origin' => $line->data_origin,
        ], [
            ['account_id' => $line->account_id, $isIn ? 'debit' : 'credit' => $amount, 'narration' => $line->description],
            ['account_id' => $counterAccountId, $isIn ? 'credit' : 'debit' => $amount, 'narration' => $line->description],
        ], $user);
        $line->update(['match_status' => 'ADJUSTED', 'matched_voucher_id' => $voucher->voucher_id, 'updated_by' => $user]);

        return $voucher;
    }

    public function statementBalance(int $accountId): ?float
    {
        $last = FinBankStatement::where('account_id', $accountId)->whereNotNull('running_balance')->orderByDesc('txn_date')->orderByDesc('stmt_line_id')->first();

        return $last ? (float) $last->running_balance : null;
    }

    public function reconcile(int $accountId, string $user, string $origin = 'LIVE'): FinBankRecon
    {
        $last = FinBankStatement::where('account_id', $accountId)->orderByDesc('txn_date')->first();
        abort_unless($last, 422, 'Belum ada mutasi rekening koran untuk akun ini.');
        $stmt = $this->statementBalance($accountId) ?? 0.0;
        $code = FinAccount::withoutGlobalScopes()->find($accountId)->account_code;
        $book = $this->ledger->accountBalance($code, $last->txn_date->toDateString());
        $unmatched = FinBankStatement::where('account_id', $accountId)->where('match_status', 'UNMATCHED')->count();
        $diff = round($stmt - $book, 2);

        return FinBankRecon::create([
            'account_id' => $accountId, 'statement_date' => $last->txn_date, 'statement_balance' => $stmt,
            'book_balance' => $book, 'difference' => $diff, 'unmatched_count' => $unmatched,
            'status' => abs($diff) < 0.01 && $unmatched === 0 ? 'RECONCILED' : 'WITH_DIFFERENCE',
            'data_origin' => $origin, 'created_by' => $user,
        ]);
    }
}
