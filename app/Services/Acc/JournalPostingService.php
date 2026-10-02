<?php

namespace App\Services\Acc;

use App\Models\Acc\FinApprovalRule;
use App\Models\Acc\FinJournal;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;

// Engine jurnal generik -- generalisasi dari pola yang sebelumnya cuma hidup
// inline di layar Down Payment v1 (diarsipkan 2026-10-02; accountId()/
// nextVoucherNumber()/FinVoucher::create()+FinVoucherLine::create() manual per
// event). Dibuat 2026-08-22 supaya Purchase Receive & Stock Out (dan modul
// lain nanti -- Delivery/Invoice, fase berikutnya) tidak menduplikasi pola
// hardcode account-code yang sama di tiap Livewire component.
// Dipakai sama seperti StockPostingService dipakai utk *_stockcard: satu-
// satunya jalur SAH menulis fin_voucher/fin_voucher_line dari proses otomatis.
//
// 2026-09-19 (Accounting Process Engine blueprint): lifecycle manual
// Draft -> Submitted -> Approved -> Posted, koreksi lewat jurnal pembalik
// (posted tidak pernah diedit), dan FinPeriodGuard di setiap titik posting.
class JournalPostingService
{
    public function __construct(private FinPeriodGuard $guard) {}

    public function nextVoucherNumber(string $journalCode, ?string $date = null): string
    {
        $prefix = 'VCH-'.$journalCode.'-'.($date ? date('Ym', strtotime($date)) : now()->format('Ym')).'-';

        return $this->nextInSeries($prefix, 4);
    }

    // Nomor jurnal manual ala blueprint: JV-2026-09-0049.
    public function nextJournalNumber(string $date): string
    {
        return $this->nextInSeries('JV-'.date('Y-m', strtotime($date)).'-', 4);
    }

    public function nextInSeries(string $prefix, int $pad): string
    {
        $last = FinVoucher::withoutGlobalScopes()->where('voucher_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('voucher_number')->value('voucher_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Posting sepasang (atau lebih) baris debit/kredit sbg satu voucher.
     * Baris bernilai nol otomatis dibuang. Menolak kalau tidak balance atau
     * tidak ada baris valid sama sekali (caller cukup skip pemanggilan kalau
     * memang tidak ada apa-apa utk dijurnal -- lihat pola isCompleteFor() di
     * PvPostingService/StockPostingService, jangan andalkan exception di sini
     * sbg alur normal "akun belum lengkap").
     *
     * $extra = kolom header tambahan (journal_kind, voucher_type, reference_no,
     * partner_name, voucher_number, data_origin, reversal_of_id, ...).
     *
     * @param  array<int, array{account_id:int, debit?:float|string, credit?:float|string, narration?:string, partner_type?:string, partner_id?:int, cc_id?:int, co_id?:int}>  $lines
     */
    public function post(string $journalCode, string $sourceType, int $sourceId, string $description, array $lines, string $actingUser, ?string $entryDate = null, array $extra = []): FinVoucher
    {
        $lines = $this->cleanLines($lines);
        $this->assertBalanced($lines);

        $journalId = FinJournal::where('journal_code', $journalCode)->value('journal_id');
        abort_unless($journalId, 404, "JournalPostingService: journal_code '{$journalCode}' tidak ditemukan.");

        $date = $entryDate ?: now()->toDateString();
        $this->guard->assertOpen($date);

        return DB::connection('tm2acc')->transaction(function () use ($journalCode, $journalId, $sourceType, $sourceId, $description, $lines, $actingUser, $date, $extra) {
            $voucher = FinVoucher::create(array_merge([
                'voucher_number' => $this->nextVoucherNumber($journalCode, $date),
                'journal_id' => $journalId,
                'journal_kind' => 'SYSTEM',
                'entry_date' => $date,
                'posting_date' => $date,
                'doc_type' => 'NEW',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'status' => 'POSTED',
                'posted_by' => $actingUser,
                'posted_at' => now(),
                'created_by' => $actingUser,
                'updated_by' => $actingUser,
            ], $extra));

            $this->writeLines($voucher, $lines);

            return $voucher;
        });
    }

    // Simpan voucher manual sbg DRAFT (header + baris), tanpa posting.
    public function saveDraft(?FinVoucher $voucher, array $header, array $lines, string $actingUser): FinVoucher
    {
        if ($voucher && $voucher->status !== 'DRAFT') {
            throw new \DomainException('Hanya voucher DRAFT yang bisa diubah. Voucher posted dikoreksi lewat jurnal pembalik.');
        }
        $lines = $this->cleanLines($lines);
        foreach ($lines as $l) {
            if ((float) ($l['debit'] ?? 0) > 0 && (float) ($l['credit'] ?? 0) > 0) {
                throw new \DomainException('Satu baris tidak boleh berisi debit dan kredit sekaligus.');
            }
        }

        return DB::connection('tm2acc')->transaction(function () use ($voucher, $header, $lines, $actingUser) {
            if ($voucher) {
                $voucher->update($header + ['updated_by' => $actingUser]);
                $voucher->lines()->delete();
            } else {
                $voucher = FinVoucher::create($header + [
                    'status' => 'DRAFT', 'doc_type' => 'NEW',
                    'posting_date' => $header['entry_date'],
                    'created_by' => $actingUser, 'updated_by' => $actingUser,
                ]);
            }
            $this->writeLines($voucher, $lines);

            return $voucher->fresh('lines');
        });
    }

    public function submit(FinVoucher $voucher, string $actingUser): void
    {
        $voucher->load('lines');
        abort_unless($voucher->status === 'DRAFT', 422, 'Hanya DRAFT yang bisa diajukan.');
        $this->assertBalanced($voucher->lines->map(fn ($l) => ['debit' => $l->debit, 'credit' => $l->credit])->all());

        if (! $this->requiresApproval('JOURNAL', $voucher->totalAmount())) {
            $voucher->update(['status' => 'APPROVED', 'submitted_by' => $actingUser, 'submitted_at' => now(), 'approved_by' => $actingUser, 'approved_at' => now(), 'updated_by' => $actingUser]);

            return;
        }
        $voucher->update(['status' => 'SUBMITTED', 'submitted_by' => $actingUser, 'submitted_at' => now(), 'updated_by' => $actingUser]);
    }

    public function approve(FinVoucher $voucher, SecUser $user): void
    {
        abort_unless($voucher->status === 'SUBMITTED', 422, 'Voucher tidak sedang menunggu persetujuan.');
        $voucher->load('lines');
        $rule = $this->ruleFor('JOURNAL', $voucher->totalAmount());
        if (! $this->canApprove($user, $rule?->approver_group)) {
            throw new \DomainException('Anda tidak berwenang menyetujui jurnal ini (approver: '.($rule?->approver_group ?? 'FINACC approve').').');
        }
        if (($rule?->segregation_of_duties ?? true) && ! $user->is_admin && in_array($user->login, [$voucher->created_by, $voucher->submitted_by], true)) {
            throw new \DomainException('Pemisahan tugas: pembuat/pengaju jurnal tidak boleh menyetujui jurnalnya sendiri.');
        }
        $voucher->update(['status' => 'APPROVED', 'approved_by' => $user->login, 'approved_at' => now(), 'updated_by' => $user->login]);
    }

    public function reject(FinVoucher $voucher, string $actingUser): void
    {
        abort_unless(in_array($voucher->status, ['SUBMITTED', 'APPROVED'], true), 422, 'Voucher ini tidak bisa dikembalikan.');
        $voucher->update(['status' => 'DRAFT', 'submitted_by' => null, 'submitted_at' => null, 'approved_by' => null, 'approved_at' => null, 'updated_by' => $actingUser]);
    }

    // Posting voucher MANUAL yang sudah tersimpan (baris sudah ada di
    // fin_voucher_line, beda dgn post() yang bikin header+baris sekaligus utk
    // posting OTOMATIS system-generated). Kas & Bank (alur lama) boleh langsung
    // dari DRAFT; jurnal umum wajib lewat APPROVED ($requireApproval).
    public function postDraft(FinVoucher $voucher, string $actingUser, bool $requireApproval = false): void
    {
        $voucher->load('lines');
        $allowed = $requireApproval ? ['APPROVED'] : ['DRAFT', 'APPROVED'];
        abort_unless(in_array($voucher->status, $allowed, true), 422, $requireApproval ? 'Jurnal harus disetujui (APPROVED) sebelum diposting.' : 'Voucher ini bukan DRAFT, tidak bisa diposting lagi.');
        abort_if($voucher->lines->isEmpty(), 422, 'Belum ada baris jurnal.');

        $totalDebit = round((float) $voucher->lines->sum('debit'), 2);
        $totalCredit = round((float) $voucher->lines->sum('credit'), 2);
        abort_if(abs($totalDebit - $totalCredit) > 0.01, 422, "Jurnal tidak balance (Debit {$totalDebit} != Kredit {$totalCredit}).");
        abort_if($totalDebit <= 0, 422, 'Jurnal tidak boleh nol.');
        $this->guard->assertOpen($voucher->entry_date);

        $voucher->update([
            'status' => 'POSTED',
            'posting_date' => $voucher->entry_date,
            'posted_by' => $actingUser,
            'posted_at' => now(),
            'updated_by' => $actingUser,
        ]);
    }

    // Blueprint: "Never edit posted records; reverse and post a corrected entry."
    public function reverse(FinVoucher $voucher, string $date, string $actingUser, string $reason = ''): FinVoucher
    {
        $voucher->load('lines', 'journal');
        abort_unless($voucher->status === 'POSTED', 422, 'Hanya voucher POSTED yang bisa dibalik.');
        abort_if($voucher->reversed_by_id, 422, 'Voucher ini sudah pernah dibalik.');

        $lines = $voucher->lines->map(fn ($l) => [
            'account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit,
            'partner_type' => $l->partner_type, 'partner_id' => $l->partner_id, 'cc_id' => $l->cc_id, 'co_id' => $l->co_id,
            'narration' => 'Pembalik: '.$l->narration,
        ])->all();

        $reversal = $this->post($voucher->journal->journal_code, 'REVERSAL', $voucher->voucher_id,
            'Pembalik '.$voucher->voucher_number.($reason ? ' — '.$reason : ''), $lines, $actingUser, $date, [
                'voucher_number' => $this->nextJournalNumber($date),
                'journal_kind' => 'REVERSAL', 'reversal_of_id' => $voucher->voucher_id,
                'reference_no' => $voucher->voucher_number, 'data_origin' => $voucher->data_origin,
            ]);
        $voucher->update(['reversed_by_id' => $reversal->voucher_id, 'updated_by' => $actingUser]);

        return $reversal;
    }

    // Jurnal akrual yang ditandai auto_reverse_date dibalik otomatis (scheduler
    // fin:auto-reverse). Mengembalikan jumlah voucher yang dibalik.
    public function runAutoReversals(string $asOf, string $actingUser): int
    {
        $due = FinVoucher::withoutGlobalScopes()->where('status', 'POSTED')->whereNull('reversed_by_id')
            ->whereNotNull('auto_reverse_date')->where('auto_reverse_date', '<=', $asOf)->get();
        foreach ($due as $v) {
            $this->reverse($v, $v->auto_reverse_date->toDateString(), $actingUser, 'auto-reversal');
        }

        return $due->count();
    }

    public function ruleFor(string $process, float $amount): ?FinApprovalRule
    {
        return FinApprovalRule::withoutGlobalScopes()->where('activate', true)->where('process_code', $process)
            ->where('min_amount', '<=', $amount)->orderByDesc('min_amount')->first();
    }

    public function requiresApproval(string $process, float $amount): bool
    {
        return (bool) $this->ruleFor($process, $amount);
    }

    public function canApprove(SecUser $user, ?string $group = null): bool
    {
        if ($user->is_admin) {
            return true;
        }
        if ($group && $user->hasGroup($group)) {
            return true;
        }

        return (bool) ($user->accessibleModules()->get('FINACC')?->can_approve);
    }

    // Item boleh override; kalau kosong, jatuh ke default kategori (doc_rmprefix1,
    // atau tabel setara lain nanti). Dipusatkan di sini supaya aturan fallback-nya
    // satu tempat, dipakai sama oleh PV & Stock Out.
    public function resolveAccount(?int $itemAccountId, ?int $categoryAccountId): ?int
    {
        return $itemAccountId ?: $categoryAccountId;
    }

    private function cleanLines(array $lines): array
    {
        return array_values(array_filter($lines, fn ($l) => ! empty($l['account_id']) && ((float) ($l['debit'] ?? 0) != 0.0 || (float) ($l['credit'] ?? 0) != 0.0)));
    }

    private function assertBalanced(array $lines): void
    {
        abort_if(empty($lines), 422, 'JournalPostingService: tidak ada baris jurnal bernilai (semua nol atau kosong).');
        $totalDebit = round(array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines)), 2);
        $totalCredit = round(array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines)), 2);
        abort_if(abs($totalDebit - $totalCredit) > 0.01, 422, "JournalPostingService: jurnal tidak balance (Debit {$totalDebit} != Kredit {$totalCredit}).");
    }

    private function writeLines(FinVoucher $voucher, array $lines): void
    {
        foreach ($lines as $line) {
            FinVoucherLine::create([
                'voucher_id' => $voucher->voucher_id,
                'account_id' => $line['account_id'],
                'partner_type' => $line['partner_type'] ?? null,
                'partner_id' => $line['partner_id'] ?? null,
                'cc_id' => $line['cc_id'] ?? null,
                'co_id' => $line['co_id'] ?? null,
                'debit' => round((float) ($line['debit'] ?? 0), 4),
                'credit' => round((float) ($line['credit'] ?? 0), 4),
                'narration' => $line['narration'] ?? null,
                'data_origin' => $voucher->data_origin ?? 'LIVE',
            ]);
        }
    }
}
