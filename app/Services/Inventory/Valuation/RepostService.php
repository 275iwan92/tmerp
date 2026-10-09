<?php

declare(strict_types=1);

namespace App\Services\Inventory\Valuation;

use App\Models\Acc\FinVoucher;
use App\Models\Inventory\InvRepostLog;
use App\Services\Acc\JournalPostingService;

// Jurnal koreksi BERTUMPUK (D18, V-07): nilai atau dokumen lampau berubah -> jurnal lama TIDAK diubah dan TIDAK ditimpa; jurnal BARU memuat
// hanya selisihnya per akun. Setiap koreksi, pembalikan, dan penilaian ulang tercatat di inv_repost_log (jurnal asal -> koreksi, sebelum / sesudah),
// dibaca laporan Repost. Tanggal jurnal koreksi = hari koreksi (periode lama yang sudah tertutup tidak dibuka).
final class RepostService
{
    public function __construct(private JournalPostingService $journals) {}

    /**
     * Koreksi satu jurnal: $newLines = isi yang SEHARUSNYA (bentuk baris post(), debit / kredit per akun); yang dijurnal hanya selisih terhadap $original.
     *
     * @param  list<array<string, mixed>>  $newLines
     * @param  array{type?: string, id?: int, no?: string}  $source
     */
    public function correct(FinVoucher $original, array $newLines, string $reason, string $login, array $source = [], ?string $date = null): ?FinVoucher
    {
        $original->loadMissing('lines', 'journal');
        $delta = $this->net($newLines);
        foreach ($this->net($original->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->debit, 'credit' => $l->credit])->all()) as $account => $net) {
            $delta[$account] = round(($delta[$account] ?? 0.0) - $net, 2);
        }
        $lines = [];
        foreach ($delta as $account => $net) {
            if (abs($net) >= 0.005) {
                $lines[] = ['account_id' => $account, 'debit' => max($net, 0), 'credit' => max(-$net, 0), 'narration' => 'Koreksi '.$original->voucher_number];
            }
        }
        if (! $lines) {
            return null;
        }

        $date ??= now()->toDateString();
        $voucher = $this->journals->post($original->journal->journal_code, 'REPOST', (int) $original->voucher_id, 'Koreksi '.$original->voucher_number.' — '.$reason, $lines, $login, $date, [
            'journal_kind' => 'CORRECTION', 'reference_no' => $original->voucher_number, 'data_origin' => $original->data_origin ?: 'LIVE']);

        $before = round((float) $original->lines->sum('debit'), 2);
        $after = round(array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $newLines)), 2);
        $this->log('CORRECTION', $original, $voucher, $before, $after, $reason, $login, $source, $date);

        return $voucher;
    }

    // Jurnal pembalik yang dibuat JournalPostingService::reverse() ikut dicatat supaya tampil di laporan Repost.
    public function reverse(FinVoucher $original, string $reason, string $login, array $source = [], ?string $date = null): FinVoucher
    {
        $date ??= now()->toDateString();
        $reversal = $this->journals->reverse($original, $date, $login, $reason);
        $total = round((float) $original->lines()->sum('debit'), 2);
        $this->log('REVERSAL', $original, $reversal, $total, 0.0, $reason, $login, $source, $date);

        return $reversal;
    }

    /**
     * Penilaian ulang baris keluar yang Average-nya berubah karena transaksi bertanggal mundur: $delta > 0 = HPP naik (Dr HPP / Cr Persediaan),
     * $delta < 0 = HPP turun (Dr Persediaan / Cr HPP). Satu jurnal per proses hitung ulang, jurnal pemakaian lama tidak diubah.
     */
    public function revalueIssued(int $materialId, float $delta, string $refNo, string $login, ?string $date = null): ?FinVoucher
    {
        $delta = round($delta, 2);
        if (abs($delta) < 0.005) {
            return null;
        }
        $stock = ValuationAccounts::stockFor($materialId);
        $cogs = ValuationAccounts::cogsFor($materialId);
        if (! $stock || ! $cogs) {
            throw new ValuationRefused(tf('Item #:id has no COGS / Inventory account (item or category); the revaluation was not posted.', ['id' => $materialId]));
        }
        $date ??= now()->toDateString();
        $voucher = $this->journals->post('USG', 'REVALUE', $materialId, 'Penilaian ulang pemakaian '.$refNo, [
            ['account_id' => $delta > 0 ? $cogs : $stock, 'debit' => abs($delta), 'narration' => 'Penilaian ulang '.$refNo],
            ['account_id' => $delta > 0 ? $stock : $cogs, 'credit' => abs($delta), 'narration' => 'Penilaian ulang '.$refNo],
        ], $login, $date, ['journal_kind' => 'CORRECTION', 'reference_no' => $refNo]);
        InvRepostLog::create(['repost_kind' => 'REVALUATION', 'source_type' => 'REVALUE', 'source_id' => $materialId, 'source_no' => $refNo, 'original_voucher_id' => null,
            'correction_voucher_id' => $voucher->voucher_id, 'correction_voucher_no' => $voucher->voucher_number, 'amount_before' => 0, 'amount_after' => abs($delta),
            'amount_delta' => $delta, 'reason' => t('Average changed by a back-dated transaction'), 'posted_on' => $date, 'created_by' => $login]);

        return $voucher;
    }

    /** @return array<int, float> account_id => debit - kredit */
    private function net(array $lines): array
    {
        $net = [];
        foreach ($lines as $l) {
            $net[(int) $l['account_id']] = round(($net[(int) $l['account_id']] ?? 0.0) + (float) ($l['debit'] ?? 0) - (float) ($l['credit'] ?? 0), 2);
        }

        return $net;
    }

    private function log(string $kind, FinVoucher $original, FinVoucher $new, float $before, float $after, string $reason, string $login, array $source, string $date): void
    {
        InvRepostLog::create(['repost_kind' => $kind, 'source_type' => $source['type'] ?? $original->source_type, 'source_id' => $source['id'] ?? $original->source_id, 'source_no' => $source['no'] ?? null,
            'original_voucher_id' => $original->voucher_id, 'original_voucher_no' => $original->voucher_number, 'correction_voucher_id' => $new->voucher_id,
            'correction_voucher_no' => $new->voucher_number, 'amount_before' => $before, 'amount_after' => $after, 'amount_delta' => round($after - $before, 2),
            'reason' => mb_substr($reason, 0, 255), 'posted_on' => $date, 'created_by' => $login]);
    }
}
