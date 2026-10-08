<?php

namespace App\Services\Inventory;

use App\Models\Acc\FinVoucher;
use App\Models\Inventory\FgStock;
use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\SfgStock;
use App\Models\Inventory\SfgStockcard;
use App\Services\Acc\JournalPostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Cancel dokumen stok POSTED (user 2026-10-08, D26 / I-57 / T17). Dokumen POSTED tidak pernah diedit atau dihapus: Cancel
// membuat dokumen PEMBALIK (doc_cat REVERSAL, arah terbalik, ref = dokumen asal) yang diposting lewat jalur normal
// (StockPostingService), jadi kartu stok bersambung (BB = EB sebelumnya) dan nilai rata-rata ikut aturan rantai. Dokumen
// asal jadi CANCELLED + menunjuk pembaliknya; jurnal pemakaian dokumen asal (Stock Out RM/SFG) ikut dibalik.
// Ditolak utuh: periode inventory tertutup / saldo jadi minus (keduanya ditolak oleh posting), Adjustment / Opname / BB
// (punya jalur sendiri), dokumen buatan proses lain (SPB -> Cancel di SPB: PvPostingService::cancel), pembalik itu sendiri.
// Pembalik Stock Out (barang kembali) bernilai sebesar nilai keluar asalnya; pembalik Stock In keluar pada harga rata-rata saat ini.
class StockReversalService
{
    public const DOC_CAT = 'REVERSAL';

    public const PREFIX = 'RV';

    private const REVERSIBLE_CATS = ['STOCK', 'RETURN'];

    // Dokumen yang dibuat tangan di layar Stock In / Out / Return; selain itu dibuat proses lain.
    private const OWN_REFS = ['', 'MANUAL', 'OUT'];

    private const CLASSES = [
        'RM' => ['header' => RmStock::class, 'card' => RmStockcard::class, 'items' => ['material_id']],
        'SFG' => ['header' => SfgStock::class, 'card' => SfgStockcard::class, 'items' => ['parts_id', 'product_id']],
        'FG' => ['header' => FgStock::class, 'card' => FgStockcard::class, 'items' => ['product_id', 'parts_id']],
    ];

    private const LINE_COLS = ['qty', 'uom_code', 'lot_no', 'location_id', 'batch_id', 'owner_type', 'owner_id', 'unit_price'];

    private static function msg(string $text, array $values = []): string
    {
        return strtr(t($text), collect($values)->mapWithKeys(fn ($v, $k) => [':'.$k => (string) $v])->all());
    }

    public function defaultReason(?string $docNo): string
    {
        return 'Kesalahan transaksi no. '.$docNo;
    }

    // Kenapa dokumen ini tidak boleh dibatalkan lewat pembalik (null = boleh).
    public function refusal(Model $h): ?string
    {
        return match (true) {
            ! $h->stat_confirm => self::msg('Only a posted document can be cancelled with a reversal. A Draft is edited or deleted.'),
            (bool) $h->stat_cancel || $h->reversed_by_doc_id => self::msg('The document is already cancelled.'),
            (bool) $h->reverses_doc_id || $h->doc_cat === self::DOC_CAT => self::msg('A reversal document cannot be cancelled again.'),
            ! in_array($h->doc_cat, self::REVERSIBLE_CATS, true) => self::msg('Adjustment, Opname and opening balance are corrected from their own screens, not by a reversal.'),
            ! in_array((string) $h->ref_doc_type, self::OWN_REFS, true) => self::msg('This document was created by another process (:ref). Cancel it from its source document.', ['ref' => $h->ref_doc_type]),
            default => null,
        };
    }

    /**
     * Keadaan untuk layar: bisa dibatalkan, alasan penolakan, pembalik (bila sudah), alasan pembatalan.
     *
     * @return array{can: bool, refusal: ?string, cancelled: bool, reversal: ?array{id: int, no: ?string}, reason: ?string, default_reason: string, reverses: ?array{id: int, no: ?string}}
     */
    public function state(string $category, ?int $docId): array
    {
        $class = self::CLASSES[$category]['header'];
        $h = $docId ? $class::find($docId) : null;
        $blank = ['can' => false, 'refusal' => null, 'cancelled' => false, 'reversal' => null, 'reason' => null, 'default_reason' => '', 'reverses' => null];
        if (! $h) {
            return $blank;
        }
        $refusal = $this->refusal($h);
        $reversal = $h->reversed_by_doc_id ? $class::find($h->reversed_by_doc_id) : null;
        $reverses = $h->reverses_doc_id ? $class::find($h->reverses_doc_id) : null;

        return ['can' => $refusal === null, 'refusal' => $refusal, 'cancelled' => (bool) $h->stat_cancel, 'reason' => $h->cancel_reason, 'default_reason' => $this->defaultReason($h->doc_no),
            'reversal' => $reversal ? ['id' => (int) $reversal->getKey(), 'no' => $reversal->doc_no] : null,
            'reverses' => $reverses ? ['id' => (int) $reverses->getKey(), 'no' => $reverses->doc_no] : null];
    }

    /** @return array{ok: bool, error: ?string, doc_id: ?int, doc_no: ?string, journal_warning: ?string, stock_warning: ?string} */
    public function reverse(string $category, int $docId, string $reason, string $login): array
    {
        $fail = fn (string $e) => ['ok' => false, 'error' => $e, 'doc_id' => null, 'doc_no' => null, 'journal_warning' => null, 'stock_warning' => null];
        $cfg = self::CLASSES[$category] ?? null;
        $reason = mb_substr(trim($reason), 0, 255);
        if (! $cfg) {
            return $fail(self::msg('Unknown stock class.'));
        }
        if ($reason === '') {
            return $fail(self::msg('Fill in the reason for cancelling.'));
        }
        $header = $cfg['header']::with('lines')->find($docId);
        if (! $header) {
            return $fail(self::msg('Document not found.'));
        }
        if ($error = $this->refusal($header)) {
            return $fail($error);
        }

        $refName = app(StockPostingService::class)->referenceName($category);
        $cards = $cfg['card']::query()->where('reference_name', $refName)->whereIn('reference_detail_id', $header->lines->modelKeys())->get()->keyBy('reference_detail_id');
        $wasIn = $header->doc_cat === 'RETURN' || (int) $header->doc_type === 1;
        $lines = $header->lines->filter(fn ($l) => $cards->has($l->getKey()))->map(function ($l) use ($cfg, $cards, $wasIn) {
            $row = $l->only([...$cfg['items'], ...self::LINE_COLS]);
            $row['qty'] = abs((float) $l->qty);
            // Barang kembali masuk bernilai sama dengan saat keluar; yang keluar lagi memakai harga rata-rata saat ini (posting).
            $row['unit_price'] = $wasIn ? (float) $l->unit_price : (float) ($cards[$l->getKey()]->avg_unitprice ?? $l->unit_price ?? 0);

            return $row;
        })->values()->all();
        if (! $lines) {
            return $fail(self::msg('This document has no stock card rows to reverse.'));
        }

        $docs = new StockDocumentService;
        $db = DB::connection('tm2inv');
        $revId = $docs->saveDraft($category, null, [
            'doc_no' => $docs->nextDocNo($category, self::PREFIX), 'doc_date' => now(), 'doc_type' => $wasIn ? -1 : 1, 'doc_cat' => self::DOC_CAT,
            'ref_doc_type' => 'REVERSAL', 'ref_doc_id' => $docId, 'ref_doc_no' => $header->doc_no, 'warehouse_id' => $header->warehouse_id,
            'notes' => mb_substr('Reversal of '.$header->doc_no.': '.$reason, 0, 255),
        ], $lines, $login);
        $cfg['header']::whereKey($revId)->update(['reverses_doc_id' => $docId]);

        // Satu pembalik per dokumen: dokumen asal dikunci dan ditandai sebelum posting.
        $claimed = $db->transaction(function () use ($cfg, $docId, $revId) {
            $h = $cfg['header']::lockForUpdate()->find($docId);
            if (! $h || $this->refusal($h)) {
                return false;
            }
            $h->forceFill(['reversed_by_doc_id' => $revId])->save();

            return true;
        });
        $discard = function () use ($cfg, $revId, $docId, $claimed) {
            if ($claimed) {
                $cfg['header']::whereKey($docId)->update(['reversed_by_doc_id' => null]);
            }
            $cfg['header']::find($revId)?->lines()->delete();
            $cfg['header']::whereKey($revId)->delete();
        };
        if (! $claimed) {
            $discard();

            return $fail(self::msg('The document is already cancelled.'));
        }

        $result = app(StockPostingService::class)->postDocument($category, $revId, $login);
        if (! $result['posted']) {
            $discard();

            return $fail((string) $result['error']);
        }

        $revNo = $cfg['header']::whereKey($revId)->value('doc_no');
        $db->transaction(fn () => $cfg['header']::whereKey($docId)->update(['stat_cancel' => true, 'flow_status' => 'CANCELLED', 'cancel_reason' => $reason,
            'cancelled_by' => $login, 'cancelled_at' => now(), 'update_by' => $login]));

        return ['ok' => true, 'error' => null, 'doc_id' => $revId, 'doc_no' => $revNo, 'journal_warning' => $this->reverseJournal($category, $header, $reason, $login), 'stock_warning' => $result['stock_warning'] ?? null];
    }

    // Jurnal pemakaian dokumen asal (Dr HPP / Cr Persediaan) dibalik; gagal = peringatan saja, stok sudah terbalik.
    private function reverseJournal(string $category, Model $h, string $reason, string $login): ?string
    {
        $warning = null;
        $vouchers = FinVoucher::withoutGlobalScopes()->where('source_type', "STOCK_OUT_{$category}")->where('source_id', $h->getKey())
            ->where('status', 'POSTED')->whereNull('reversed_by_id')->get();
        foreach ($vouchers as $v) {
            try {
                app(JournalPostingService::class)->reverse($v, now()->toDateString(), $login, "Cancel {$h->doc_no}: {$reason}");
            } catch (\Throwable $e) {
                $warning = trim($warning.' '.self::msg('Journal :no has not been reversed: :error', ['no' => $v->voucher_number, 'error' => $e->getMessage()]));
            }
        }

        return $warning;
    }

    // Duplicate: Draft baru berisi salinan dokumen (untuk dibetulkan lalu di-Confirm). Nomor baru, tanggal hari ini.
    public function duplicate(string $category, int $docId, string $login): ?int
    {
        $cfg = self::CLASSES[$category] ?? null;
        $h = $cfg ? $cfg['header']::with('lines')->find($docId) : null;
        if (! $h || $h->doc_cat === self::DOC_CAT) {
            return null;
        }
        $docs = new StockDocumentService;
        $prefix = str_contains((string) $h->doc_no, '/') ? strtok($h->doc_no, '/') : 'DUP';
        $lines = $h->lines->map(fn ($l) => $l->only([...$cfg['items'], ...self::LINE_COLS, 'action_type']))->all();

        return $docs->saveDraft($category, null, ['doc_no' => $docs->nextDocNo($category, $prefix), 'doc_date' => now()->toDateString()]
            + $h->only(['doc_type', 'doc_cat', 'ref_doc_type', 'ref_doc_id', 'ref_doc_no', 'wo_id', 'wo_no', 'warehouse_id', 'issued_to_dept', 'process_code', 'notes']), $lines, $login);
    }
}
