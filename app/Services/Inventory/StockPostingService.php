<?php

namespace App\Services\Inventory;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\InvPeriod;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\SfgStock;
use App\Models\Inventory\SfgStockcard;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Services\Acc\JournalPostingService;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;

// Port dari trigger legacy trg_rm_stock_confirm/trg_fg_stock_confirm/
// trg_sfg_stock_confirm (database/legacy/tminv_20260722_2018_sd.sql baris
// ~1278/1347/1430) yang TIDAK ikut ter-import ke DB live (SHOW TRIGGERS
// kosong) -- lihat docs/sidebarmenustandard.md #6a utk alasan kenapa
// logikanya diport ke sini (App\Services\*, bukan trigger SQL, konsisten
// dgn Costing/Quotation/Flow Engine). Satu-satunya jalur SAH utk menulis
// *_stockcard -- Livewire component TIDAK BOLEH insert langsung (INV_TIER_4 #7).
// URUTAN (user 2026-10-02): baris kartu berurut (doc_date, doc_id). Dokumen bertanggal lebih awal dari baris terakhir
// disisipkan di tempatnya dan baris sesudahnya dihitung ulang (StockCardChain); dokumen yang sudah diposting tidak
// pernah diubah -- yang salah dikoreksi lewat Stock Adjustment.
// AUTO-JURNAL PEMAKAIAN (2026-08-22, riset ERP Odoo/SAP/D365/Oracle; diperluas
// ke SFG 2026-08-22 sore): Stock Out RM/SFG sungguhan (docType OUT) otomatis
// posting Dr HPP (cogs_account_id) / Cr Persediaan (stock_asset_account_id) ke
// journal_code 'USG'. HANYA docType OUT -- Adjustment/Opname/Return/BB SENGAJA
// belum dijurnal (beda makna akuntansi, bukan konsumsi produksi). FG TETAP
// tidak lewat Stock Out sama sekali (lihat komentar stock-out.blade.php) jadi
// tidak relevan di sini. Akun RM: item override (mat_raw) -> fallback
// kategori (doc_rmprefix1). Akun SFG: item override (prod_part) SAJA -- tidak
// ada tabel fallback kategori setara doc_rmprefix1 utk SFG, jadi part yang
// belum diisi akunnya akan selalu dilewati+peringatan sampai diisi manual.
// Lihat JournalPostingService & PvPostingService::postPurchaseJournal() utk
// pola yg sama di sisi pembelian.
class StockPostingService
{
    private const CATEGORY_MAP = [
        'RM' => ['header' => RmStock::class, 'card' => RmStockcard::class, 'itemKey' => 'material_id', 'itemModel' => MatRaw::class, 'itemPk' => 'material_id'],
        'FG' => ['header' => FgStock::class, 'card' => FgStockcard::class, 'itemKey' => 'product_id', 'itemModel' => ProdProduct::class, 'itemPk' => 'id_product'],
        'SFG' => ['header' => SfgStock::class, 'card' => SfgStockcard::class, 'itemKey' => 'parts_id', 'itemModel' => ProdPart::class, 'itemPk' => 'id_part'],
    ];

    public function referenceName(string $category): string
    {
        return match ($category) {
            'FG' => 'tm2inv.fg_stock_d',
            'SFG' => 'tm2inv.sfg_stock_d',
            default => 'tm2inv.rm_stock_d',
        };
    }

    // Pesan baru: teks English lewat kamus, nilai data disisipkan sesudahnya (tidak tercatat di kamus).
    private static function msg(string $text, array $values = []): string
    {
        return strtr(t($text), collect($values)->mapWithKeys(fn ($v, $k) => [':'.$k => (string) $v])->all());
    }

    /**
     * Posting dokumen: sisipkan baris kartu stok per baris dokumen di tempatnya menurut tanggal, hitung ulang saldo
     * berjalan posisi itu (StockCardChain), kunci header jadi POSTED.
     * Ditolak utuh (tidak ada yang tertulis): periode inventory tertutup, atau saldo jadi minus -- di baris itu
     * maupun di baris yang bertanggal sesudahnya.
     * Return ['posted' => bool, 'error' => ?string, 'journal_warning' => ?string, 'revaluation' => ?array, 'stock_warning' => ?string].
     */
    public function postDocument(string $category, int $docId, string $actingUser): array
    {
        $map = self::CATEGORY_MAP[$category] ?? null;
        abort_if(! $map, 404, "Kelas stok tidak dikenal: {$category}");

        // Diisi di dalam transaksi (butuh harga rata-rata per baris, cuma tersedia di
        // situ), dipakai SETELAH commit -- fin_voucher/fin_voucher_line hidup di
        // koneksi tm2acc yang beda, jangan disatukan ke transaksi tm2inv di atas.
        $usageJournalRows = [];
        // Baris keluar bertanggal sesudah dokumen ini yang harga rata-ratanya berubah karena hitung ulang.
        $revalued = ['rows' => 0, 'value' => 0.0];

        try {
            $result = DB::connection('tm2inv')->transaction(function () use ($map, $category, $docId, $actingUser, &$usageJournalRows, &$revalued) {
                /** @var RmStock|FgStock|SfgStock $header */
                $header = $map['header']::with('lines')->lockForUpdate()->findOrFail($docId);

                if ($header->stat_confirm) {
                    return ['posted' => false, 'error' => t('The document has already been posted before.')];
                }
                if ($header->stat_cancel) {
                    return ['posted' => false, 'error' => t('The document is already cancelled, it cannot be posted.')];
                }
                // inventory_tier2 B-3 + user 2026-10-02: tanggal di periode tertutup ditolak; koreksinya = Stock Adjustment
                // bertanggal di periode yang masih terbuka. Baris sesudahnya ikut terlindung karena semuanya bertanggal lebih baru.
                $closed = InvPeriod::closedThrough();
                if ($closed && $header->doc_date <= $closed) {
                    return ['posted' => false, 'error' => self::msg('The document date is in a closed inventory period (closed through :date). Enter a Stock Adjustment dated in an open period instead.',
                        ['date' => $closed->toDateString()])];
                }

                $cat = $this->normalizeDocCat($header->doc_cat);
                $itemKey = $map['itemKey'];
                $cardModel = $map['card'];
                $refName = $this->referenceName($category);
                $netByItem = [];

                foreach ($header->lines as $line) {
                    if ($line->action_type === 'PURCHASE') {
                        continue;
                    }
                    if ((float) $line->qty === 0.0 && $cat !== 'BB') {
                        continue;
                    }

                    $qtyFinal = $this->qtyFinal($cat, (float) $line->qty, (int) $header->doc_type);
                    $itemId = $line->{$itemKey};
                    $locationId = $line->location_id;

                    // Idempotensi: kalau baris ini sudah pernah diposting (reference match), lewati -- jangan dobel.
                    $already = $cardModel::where('reference_name', $refName)->where('reference_detail_id', $line->getKey())->exists();
                    if ($already) {
                        continue;
                    }

                    $docType = match (true) {
                        $cat === 'BB' => 'BB',
                        $cat === 'RETURN' => 'IN',
                        in_array($cat, ['AD', 'OP'], true) => 'AD',
                        (int) $header->doc_type === 1 => 'IN',
                        default => 'OUT',
                    };

                    // Pemilik v2.01: stok milik customer / supplier (titipan, consignment) tidak dinilai dan tidak dijurnal.
                    $ownerType = $line->owner_type ?: 'COMPANY';
                    $valued = $ownerType === 'COMPANY';
                    $unitPrice = $valued ? (float) ($line->unit_price ?? 0) : 0.0;
                    $qtyIn = ($cat !== 'AD' && $cat !== 'OP' && ((int) $header->doc_type === 1 || $cat === 'RETURN')) ? abs((float) $line->qty) : 0;
                    $qtyOut = ($cat !== 'AD' && $cat !== 'OP' && (int) $header->doc_type === -1 && $cat !== 'RETURN') ? abs((float) $line->qty) : 0;
                    $qtyAdj = in_array($cat, ['AD', 'OP'], true) ? $qtyFinal : 0;

                    // Baris disisipkan di tempatnya menurut tanggal dokumen (sesudah baris lain bertanggal-jam sama), lalu
                    // StockCardChain menghitung saldo baris ini dan SEMUA baris sesudahnya (aturan user 2026-10-02).
                    // Valuasi AVG bergerak (moving average) -- FIFO/LIFO (inv_stock_layer) belum diimplementasikan di v1.
                    $card = $cardModel::create([
                        'doc_date' => $header->doc_date,
                        $itemKey => $itemId,
                        'doc_type' => $docType,
                        'location_id' => $locationId,
                        'warehouse_id' => $header->warehouse_id,
                        'lot_no' => $line->lot_no,
                        'qty' => $qtyFinal,
                        'uom_code' => $line->uom_code,
                        'reference_detail_id' => $line->getKey(),
                        'reference_name' => $refName,
                        'qty_bb' => 0,
                        'qty_in' => $qtyIn,
                        'qty_out' => $qtyOut,
                        'qty_adj' => $qtyAdj,
                        'qty_eb' => 0,
                        'value_in' => $valued && $qtyIn > 0 ? $qtyIn * $unitPrice : null,
                        'batch_id' => $line->batch_id,
                        'owner_type' => $ownerType,
                        'owner_id' => $line->owner_id,
                        'period_id' => $header->period_id,
                        'create_by' => $actingUser,
                        'update_by' => $actingUser,
                    ]);
                    $chain = StockCardChain::recalculate($category, $card, true);
                    $card->refresh();

                    if ($chain['negative'] && ! in_array($cat, ['AD', 'OP'], true)) {
                        throw new StockPostingRefused((int) $chain['negative']['doc_id'] === (int) $card->getKey()
                            ? "Saldo item #{$itemId} di lokasi #{$locationId} akan negatif ({$chain['negative']['qty']}) -- posting dibatalkan."
                            : self::msg('Item #:item in location #:loc would go below zero on :date (:qty) because of movements dated after this document -- posting refused.',
                                ['item' => $itemId, 'loc' => $locationId, 'date' => $chain['negative']['doc_date'], 'qty' => $chain['negative']['qty']]));
                    }
                    $revalued['rows'] += $chain['revalued_rows'];
                    $revalued['value'] += $chain['revalued_value'];

                    // Auto-jurnal pemakaian (2026-08-22, diperluas ke SFG 2026-08-22):
                    // cuma Stock Out sungguhan (docType OUT, bukan BB/RETURN/AD/OP) yang
                    // masuk HPP -- Adjustment/Opname punya makna akuntansi beda (koreksi,
                    // bukan konsumsi produksi) & sengaja TIDAK dijurnal otomatis di sini.
                    // SFG pakai ProdPart::resolveCogsAccountId()/resolveStockAccountId()
                    // (BACKLOG §12.3, 2026-09-11) -- fallback 3-lapis part -> product ->
                    // prod_category.default_*_account_id. Kalau semua lapis kosong, baris ini
                    // dilewati + masuk peringatan "belum lengkap", bukan silent skip.
                    // Nilai pakai harga rata-rata yang berlaku saat barang ini keluar (baris
                    // keluar membawa harga rata-rata baris sebelumnya).
                    // Pembalik penerimaan (Cancel SPB, ref PV_CANCEL) bukan pemakaian produksi: jurnalnya = pembalik jurnal
                    // pembelian (PvPostingService::cancel), jadi TIDAK ikut jurnal HPP di sini.
                    if ($valued && in_array($category, ['RM', 'SFG'], true) && $docType === 'OUT' && ! in_array($header->ref_doc_type, ['PV_CANCEL', 'REVERSAL'], true)) {
                        $usageJournalRows[] = ['category' => $category, 'item_id' => $itemId, 'qty' => abs($qtyFinal), 'unit_cost' => (float) $card->avg_unitprice];
                    }

                    $netByItem[$itemId] = ($netByItem[$itemId] ?? 0) + $qtyFinal;
                }

                $header->update([
                    'stat_confirm' => true,
                    'flow_status' => 'POSTED',
                    'posted_by' => $actingUser,
                    'posted_at' => now(),
                    'update_by' => $actingUser,
                ]);

                // Sinkronkan cur_stock master item (netto delta dokumen ini, dijumlah semua lokasi).
                // NULL-safe: ->increment() gagal diam-diam (NULL + x = NULL di SQL) -- banyak
                // mat_raw.cur_stock legacy masih NULL (5893 baris blm pernah disentuh
                // stok), jadi wajib COALESCE, bukan increment() polos.
                foreach ($netByItem as $itemId => $delta) {
                    $map['itemModel']::where($map['itemPk'], $itemId)
                        ->update(['cur_stock' => DB::raw('COALESCE(cur_stock, 0) + ('.(float) $delta.')')]);
                }

                return ['posted' => true, 'error' => null];
            });
        } catch (StockPostingRefused $e) {
            // Transaksi sudah di-rollback: baris kartu baris-baris sebelumnya dan hitung ulangnya ikut batal.
            return ['posted' => false, 'error' => $e->getMessage()];
        }

        if ($result['posted'] && $revalued['rows'] > 0 && abs($revalued['value']) >= 0.005) {
            // Jurnal pemakaian baris-baris itu sudah diposting dengan harga lama dan TIDAK diubah di sini (keputusan
            // accounting belum ada: jurnal koreksi otomatis atau tidak) -- selisihnya dilaporkan, tidak disembunyikan.
            $result['revaluation'] = $revalued;
            $result['stock_warning'] = self::msg('The average price of :n later stock-out row(s) changed; their value changed by :value. Journals already posted for them were not changed.',
                ['n' => $revalued['rows'], 'value' => Locale::decimal($revalued['value'], 2)]);
        }

        if ($result['posted'] && ! empty($usageJournalRows)) {
            $result['journal_warning'] = $this->postUsageJournal($category, $docId, $usageJournalRows, $actingUser);
        }

        return $result;
    }

    // Dr HPP (cogs_account_id) / Cr Persediaan (stock_asset_account_id) per
    // item, dikelompokkan per pasangan akun. Akun kosong (item, dan utk RM
    // fallback kategori doc_rmprefix1 -- SFG tak punya tabel fallback setara)
    // DILEWATI dari jurnal, bukan membatalkan posting stok yang sudah terjadi
    // -- caller cukup tampilkan peringatannya.
    protected function postUsageJournal(string $category, int $docId, array $usageJournalRows, string $actingUser): ?string
    {
        $header = self::CATEGORY_MAP[$category]['header']::find($docId);
        $rows = [];
        $unmapped = 0;

        foreach ($usageJournalRows as $r) {
            if ($r['category'] === 'SFG') {
                $part = ProdPart::find($r['item_id']);
                $cogsAccountId = $part?->resolveCogsAccountId();
                $stockAccountId = $part?->resolveStockAccountId();
            } else {
                $material = MatRaw::find($r['item_id']);
                $prefix1 = $material ? MatCategory::level(1)->where('category_name', $material->category1)->first() : null;
                $cogsAccountId = app(JournalPostingService::class)->resolveAccount($material?->cogs_account_id, $prefix1?->default_coa_account_id);
                $stockAccountId = app(JournalPostingService::class)->resolveAccount($material?->stock_asset_account_id, $prefix1?->default_stock_asset_account_id);
            }

            $value = round($r['qty'] * $r['unit_cost'], 2);

            if (! $cogsAccountId || ! $stockAccountId || $value <= 0) {
                $unmapped++;

                continue;
            }

            $key = $cogsAccountId.'-'.$stockAccountId;
            $rows[$key]['cogs_account_id'] = $cogsAccountId;
            $rows[$key]['stock_account_id'] = $stockAccountId;
            $rows[$key]['value'] = ($rows[$key]['value'] ?? 0) + $value;
        }

        if (! empty($rows)) {
            $label = $category === 'SFG' ? 'Pemakaian barang setengah jadi' : 'Pemakaian bahan baku';
            $lines = [];
            foreach ($rows as $row) {
                $lines[] = ['account_id' => $row['cogs_account_id'], 'debit' => $row['value'], 'narration' => "{$label} {$header?->doc_no}"];
                $lines[] = ['account_id' => $row['stock_account_id'], 'credit' => $row['value'], 'narration' => "{$label} {$header?->doc_no}"];
            }

            app(JournalPostingService::class)->post('USG', "STOCK_OUT_{$category}", $docId, "{$label} {$header?->doc_no}", $lines, $actingUser, $header?->doc_date?->toDateString());
        }

        return $unmapped > 0
            ? tf(':n lines have no COGS/Inventory account yet (item or category) -- the usage journal is not complete for those lines.', ['n' => $unmapped])
            : null;
    }

    private function normalizeDocCat(?string $docCat): string
    {
        return match (true) {
            in_array($docCat, ['ADJUST', 'ADJ', 'AD'], true) => 'AD',
            in_array($docCat, ['OPN', 'OPNAME', 'OP'], true) => 'OP',
            $docCat === 'RETURN' => 'RETURN',
            $docCat === 'BB' => 'BB',
            default => (string) $docCat,
        };
    }

    private function qtyFinal(string $cat, float $qty, int $docType): float
    {
        return match (true) {
            in_array($cat, ['AD', 'OP'], true) => $qty, // sudah signed dari input (fisik - sistem)
            $cat === 'RETURN' => abs($qty),
            default => $docType === -1 ? -abs($qty) : abs($qty),
        };
    }
}
