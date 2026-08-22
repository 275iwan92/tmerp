<?php

namespace App\Services\Acc;

use App\Models\Acc\FinJournal;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use Illuminate\Support\Facades\DB;

// Engine jurnal generik -- generalisasi dari pola yang sebelumnya cuma hidup
// inline di resources/views/pages/finance/dp-invoice.blade.php (accountId()/
// nextVoucherNumber()/FinVoucher::create()+FinVoucherLine::create() manual per
// event). Dibuat 2026-08-22 supaya Purchase Receive & Stock Out (dan modul
// lain nanti -- Delivery/Invoice, fase berikutnya) tidak menduplikasi pola
// hardcode account-code yang sama di tiap Livewire component.
// Dipakai sama seperti StockPostingService dipakai utk *_stockcard: satu-
// satunya jalur SAH menulis fin_voucher/fin_voucher_line dari proses otomatis.
class JournalPostingService
{
    public function nextVoucherNumber(string $journalCode): string
    {
        $prefix = 'VCH-'.$journalCode.'-'.now()->format('Ym').'-';
        $seq = FinVoucher::where('voucher_number', 'like', $prefix.'%')->lockForUpdate()->count() + 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Posting sepasang (atau lebih) baris debit/kredit sbg satu voucher.
     * Baris bernilai nol otomatis dibuang. Menolak kalau tidak balance atau
     * tidak ada baris valid sama sekali (caller cukup skip pemanggilan kalau
     * memang tidak ada apa-apa utk dijurnal -- lihat pola isCompleteFor() di
     * PvPostingService/StockPostingService, jangan andalkan exception di sini
     * sbg alur normal "akun belum lengkap").
     *
     * @param  array<int, array{account_id:int, debit?:float|string, credit?:float|string, narration?:string, partner_type?:string, partner_id?:int, cc_id?:int, co_id?:int}>  $lines
     */
    public function post(string $journalCode, string $sourceType, int $sourceId, string $description, array $lines, string $actingUser, ?string $entryDate = null): FinVoucher
    {
        $lines = array_values(array_filter($lines, fn ($l) => (float) ($l['debit'] ?? 0) != 0.0 || (float) ($l['credit'] ?? 0) != 0.0));
        abort_if(empty($lines), 422, 'JournalPostingService: tidak ada baris jurnal bernilai (semua nol atau kosong).');

        $totalDebit = round(array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines)), 2);
        $totalCredit = round(array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines)), 2);
        abort_if(abs($totalDebit - $totalCredit) > 0.01, 422, "JournalPostingService: jurnal tidak balance (Debit {$totalDebit} != Kredit {$totalCredit}).");

        $journalId = FinJournal::where('journal_code', $journalCode)->value('journal_id');
        abort_unless($journalId, 404, "JournalPostingService: journal_code '{$journalCode}' tidak ditemukan.");

        return DB::connection('tm2acc')->transaction(function () use ($journalCode, $journalId, $sourceType, $sourceId, $description, $lines, $actingUser, $entryDate) {
            $date = $entryDate ?: now()->toDateString();

            $voucher = FinVoucher::create([
                'voucher_number' => $this->nextVoucherNumber($journalCode),
                'journal_id' => $journalId,
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
            ]);

            foreach ($lines as $line) {
                FinVoucherLine::create([
                    'voucher_id' => $voucher->voucher_id,
                    'account_id' => $line['account_id'],
                    'partner_type' => $line['partner_type'] ?? null,
                    'partner_id' => $line['partner_id'] ?? null,
                    'cc_id' => $line['cc_id'] ?? null,
                    'co_id' => $line['co_id'] ?? null,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'narration' => $line['narration'] ?? null,
                ]);
            }

            return $voucher;
        });
    }

    // Posting voucher MANUAL yang sudah tersimpan sbg DRAFT (baris sudah ada
    // di fin_voucher_line, beda dgn post() yang bikin header+baris sekaligus
    // utk posting OTOMATIS system-generated). Dipakai layar Jurnal Kas & Bank
    // -- satu2nya layar di codebase ini yg punya alur Simpan Draft -> Post
    // manual (DP Invoice dkk langsung POSTED, tak pernah draft).
    public function postDraft(FinVoucher $voucher, string $actingUser): void
    {
        $voucher->load('lines');
        abort_unless($voucher->status === 'DRAFT', 422, 'Voucher ini bukan DRAFT, tidak bisa diposting lagi.');
        abort_if($voucher->lines->isEmpty(), 422, 'Belum ada baris jurnal.');

        $totalDebit = round((float) $voucher->lines->sum('debit'), 2);
        $totalCredit = round((float) $voucher->lines->sum('credit'), 2);
        abort_if(abs($totalDebit - $totalCredit) > 0.01, 422, "Jurnal tidak balance (Debit {$totalDebit} != Kredit {$totalCredit}).");
        abort_if($totalDebit <= 0, 422, 'Jurnal tidak boleh nol.');

        $voucher->update([
            'status' => 'POSTED',
            'posted_by' => $actingUser,
            'posted_at' => now(),
            'updated_by' => $actingUser,
        ]);
    }

    // Item boleh override; kalau kosong, jatuh ke default kategori (doc_rmprefix1,
    // atau tabel setara lain nanti). Dipusatkan di sini supaya aturan fallback-nya
    // satu tempat, dipakai sama oleh PV & Stock Out.
    public function resolveAccount(?int $itemAccountId, ?int $categoryAccountId): ?int
    {
        return $itemAccountId ?: $categoryAccountId;
    }
}
