<?php

namespace App\Services\Pur;

use App\Models\Acc\FinVoucher;
use App\Models\Inventory\RmStock;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PvReceipt;
use App\Models\Sys\SysConstant;
use App\Services\Acc\JournalPostingService;
use App\Services\Inventory\ReceiptBatchService;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// PvPostingService -- gerbang POST (PROC_TIER_2 §A-4.5) + rekalkulasi PER PO
// yang tersentuh (PV-08, purchasereceive_delta_multipo.md), krn satu SPB kini boleh
// menyentuh banyak PO sekaligus.
//
// INTEGRASI STOK FISIK (kontrak PROC_TIER_3 §5.1, rm_stock_doc_id): stok
// diposting LEBIH DULU lewat App\Services\Inventory\StockDocumentService +
// StockPostingService (jalur SAH satu-satunya utk menulis rm_stock*, sama
// persis dgn Stock In manual) -- SEBELUM status PV diubah jadi POSTED, supaya
// tidak pernah ada PV berstatus POSTED tanpa pergerakan stok. tm2inv & tm2pur
// beda koneksi (CLAUDE.md §2, tak bisa satu transaction), jadi urutannya:
// stok dulu (postToInventory, transaksinya sendiri) -> baru transaction tm2pur
// (status+rm_stock_doc_id+rekalkulasi PO) -> jurnal pembelian (tm2acc, soal-fail
// spt sebelumnya). Kalau posting stok gagal, PV TIDAK berubah status sama sekali.
//
// AUTO-JURNAL PEMBELIAN (2026-08-22, riset ERP Odoo/SAP/D365/Oracle): setiap
// baris is_stock=1 diposting Dr Persediaan (stock_asset_account_id) / Cr GR/IR
// Clearing (gr_ir_account_id) -- BUKAN langsung Cr Hutang Usaha, krn Purchase
// Invoice supplier belum dibangun (fin_invoice_line, PROC_TIER_3 §7.2 masih
// TODO) -- saat modul itu ada nanti, pelunasannya Dr GR/IR / Cr Hutang Usaha,
// pola standar 4 ERP besar itu utk celah "barang sudah masuk, tagihan belum".
// Akun diambil dari mat_raw per item, fallback ke doc_rmprefix1 per
// kategori kalau item belum di-set (JournalPostingService::resolveAccount()).
// Baris yang akun-nya (item maupun kategori) sama2 kosong DILEWATI dari jurnal
// (bukan diblokir) -- PV tetap boleh diposting scr fisik/stok, cuma dikasih
// peringatan supaya officer tahu jurnalnya belum lengkap, bukan tersembunyi.
class PvPostingService
{
    public static function blockers(PvReceipt $pv): array
    {
        $blockers = [];

        foreach ($pv->lines as $line) {
            if ($line->qc_qty_status !== 'DONE') {
                $blockers[] = tf('Line :line has not had its qty QC yet.', ['line' => $line->line_no]);

                continue;
            }

            // Baris URGENT (v2.01, Q-03): QC mutu yang masih PENDING tidak menahan posting, tetapi alasan, WO tujuan, hak penanda dan
            // persetujuan FSC / food grade wajib beres. Baris biasa tetap menunggu hasil sampling.
            if ($line->is_urgent) {
                $blockers = array_merge($blockers, UrgentReceiptService::postBlockers($line));
            } elseif ($line->qc_quality_status === 'PENDING') {
                $blockers[] = tf('Line :line has not had its quality QC yet.', ['line' => $line->line_no]);

                continue;
            }

            if ($line->is_tol_exceeded && ! $line->tol_override_by) {
                $blockers[] = tf('Line :line exceeds the tolerance and has not been overridden.', ['line' => $line->line_no]);
            }
        }

        return $blockers;
    }

    public static function post(PvReceipt $pv): array
    {
        if ($pv->status !== 'QC_PENDING' && $pv->status !== 'DRAFT') {
            return ['ok' => false, 'message' => t('The document no longer has status DRAFT/QC_PENDING.')];
        }

        $blockers = static::blockers($pv);

        if (! empty($blockers)) {
            return ['ok' => false, 'message' => implode(' ', $blockers)];
        }

        $login = Auth::user()->login;
        UrgentReceiptService::prepareForPost($pv);
        // Nomor SPB lahir saat posting (user 2026-10-02) supaya urutannya tidak bolong oleh Draft yang dihapus. Dokumen stok
        // dan jurnal merujuk nomor ini, jadi ditetapkan dulu di memori; baru tersimpan bersama status POSTED di bawah.
        $numbered = (bool) $pv->doc_no;
        $pv->doc_no = $pv->doc_no ?: PvReceipt::nextDocNo((string) ($pv->i_receipt_type ?: 'PO'));

        try {
            [$stockDocId, $stockWarning] = static::postToInventory($pv, $login);
        } catch (\Throwable $e) {
            if (! $numbered) {
                $pv->doc_no = null;
            }

            return ['ok' => false, 'message' => tf('Failed to post the physical stock: :error', ['error' => $e->getMessage()])];
        }

        DB::connection('tm2pur')->transaction(function () use ($pv, $login, $stockDocId) {
            $pv->update([
                'status' => 'POSTED',
                'posted_at' => now(),
                'posted_by' => $login,
                'rm_stock_doc_id' => $stockDocId,
                'updated_by' => $login,
            ]);

            // PV-08: rekalkulasi SETIAP PO yang tersentuh, bukan asumsi satu PO.
            $poIds = DB::connection('tm2pur')->table('pv_line')->where('pv_id', $pv->pv_id)->select('c_po_id')->distinct()->pluck('c_po_id');

            foreach ($poIds as $poId) {
                static::recalcPo($poId);
            }
        });

        $journalWarning = static::postPurchaseJournal($pv, $login);
        // Batch lahir dari penerimaan ini (v2.01): URGENT = Conditional, biasa = Released. Gagal = peringatan saja.
        $batchWarning = implode(' ', ReceiptBatchService::forPostedReceipt($pv->refresh(), $login));

        return ['ok' => true, 'message' => null, 'journal_warning' => $journalWarning, 'stock_warning' => trim($stockWarning.' '.$batchWarning) ?: null];
    }

    // CANCEL SPB (user 2026-10-01) -- pengganti trigger legacy pur_prcv_detail AFTER DELETE (hitung ulang barang masuk
    // PO saat penerimaan dihapus). SPB POSTED tidak dihapus, tapi dibalik dengan urutan yang sama seperti post():
    // (1) stok keluar lagi lewat dokumen Stock Out RM ber-ref PV_CANCEL (StockPostingService, jalur sah satu-satunya
    // ke kartu stok; ditolak bila saldo jadi negatif = barang sudah terpakai -> pakai Purchase Return / Adjustment),
    // (2) tm2pur: status CANCELLED + hitung ulang semua PO tersentuh (recalcPo hanya menghitung PV POSTED),
    // (3) jurnal Dr Persediaan / Cr GR/IR dibalik (JournalPostingService::reverse) -- gagal di sini = peringatan saja,
    // sama seperti jurnal saat post.
    public static function cancel(PvReceipt $pv, string $reason): array
    {
        if ($pv->status !== 'POSTED') {
            return ['ok' => false, 'message' => t('Only a POSTED receipt can be cancelled (a Draft is simply deleted).')];
        }
        if (mb_strlen(trim($reason)) < 10) {
            return ['ok' => false, 'message' => t('The cancellation reason is required, at least 10 characters.')];
        }
        if ($pv->is_handed_over) {
            return ['ok' => false, 'message' => t('This receipt has already been handed over / invoiced — it cannot be cancelled.')];
        }
        $returns = DB::connection('tm2pur')->table('prt_return')->where('pv_id', $pv->pv_id)->where('status', '!=', 'CANCELLED')->pluck('doc_no');
        if ($returns->isNotEmpty()) {
            return ['ok' => false, 'message' => tf('There is already a Purchase Return (:list) from this receipt — cancel the return first.', ['list' => $returns->implode(', ')])];
        }

        // Penerimaan yang qty-nya sudah dikoreksi permintaan kirim kekurangan tidak dibalik utuh (stok dan jurnal koreksinya sudah terpisah).
        if (ShortageRequestService::hasCorrections($pv)) {
            return ['ok' => false, 'message' => t('A shortage request has already corrected this receipt — it cannot be cancelled. Use Purchase Return / Stock Adjustment.')];
        }

        $login = Auth::user()->login;

        try {
            [$revDocId, $stockWarning] = static::reverseInventory($pv, $login);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => tf('Failed to reverse the stock: :error', ['error' => $e->getMessage()])];
        }

        DB::connection('tm2pur')->transaction(function () use ($pv, $login, $revDocId, $reason) {
            $pv->update(['status' => 'CANCELLED', 'cancel_reason' => mb_substr(trim($reason), 0, 255), 'cancelled_at' => now(),
                'cancelled_by' => $login, 'rm_stock_rev_doc_id' => $revDocId, 'updated_by' => $login]);
            // Bahan dari customer tidak punya PO (c_po_id kosong) -> dilewati.
            $poIds = DB::connection('tm2pur')->table('pv_line')->where('pv_id', $pv->pv_id)->whereNotNull('c_po_id')->select('c_po_id')->distinct()->pluck('c_po_id');
            foreach ($poIds as $poId) {
                static::recalcPo($poId);
            }
        });

        $warning = null;
        // Jurnal penerimaan + koreksi kekurangan subkon (PV_SHORT) ikut dibalik semua.
        $vouchers = FinVoucher::withoutGlobalScopes()->whereIn('source_type', ['PV', 'PV_SHORT'])->where('source_id', $pv->pv_id)
            ->where('status', 'POSTED')->whereNull('reversed_by_id')->get();
        foreach ($vouchers as $voucher) {
            try {
                app(JournalPostingService::class)->reverse($voucher, now()->toDateString(), $login, "Cancel {$pv->doc_no}: ".trim($reason));
            } catch (\Throwable $e) {
                $warning = trim($warning.' '.tf('Journal :no has not been reversed: :error', ['no' => $voucher->voucher_number, 'error' => $e->getMessage()]));
            }
        }

        return ['ok' => true, 'message' => null, 'journal_warning' => $warning, 'stock_warning' => $stockWarning];
    }

    // Stock Out RM berisi baris yang persis sama dgn dokumen Stock In hasil post() (item, lokasi, lot, qty).
    // Kembalian: [id dokumen stok | null, peringatan nilai stok | null].
    protected static function reverseInventory(PvReceipt $pv, string $login): array
    {
        $in = $pv->rm_stock_doc_id ? RmStock::with('lines')->find($pv->rm_stock_doc_id) : null;
        if (! $in || ! $in->stat_confirm || $in->lines->isEmpty()) {
            return [null, null];
        }

        $docService = app(StockDocumentService::class);
        $docId = $docService->saveDraft('RM', null, [
            'doc_no' => $docService->nextDocNo('RM', 'OUT'),
            'doc_date' => now()->toDateString(),
            'doc_type' => -1,
            'doc_cat' => 'STOCK',
            'ref_doc_type' => 'PV_CANCEL',
            'ref_doc_id' => $pv->pv_id,
            'ref_doc_no' => $pv->doc_no,
            'warehouse_id' => $in->warehouse_id,
            'notes' => "Pembalik Penerimaan Barang {$pv->doc_no} (Cancel SPB, dokumen masuk {$in->doc_no})",
        ], $in->lines->map(fn ($l) => ['material_id' => $l->material_id, 'qty' => (float) $l->qty, 'uom_code' => $l->uom_code,
            'unit_price' => (float) $l->unit_price, 'lot_no' => $l->lot_no, 'location_id' => $l->location_id])->all(), $login);

        $result = app(StockPostingService::class)->postDocument('RM', $docId, $login);
        if (! $result['posted']) {
            $docService->cancel('RM', $docId, $login);

            throw new \RuntimeException(($result['error'] ?? t('unknown')).' '.t('The goods have probably been used — use Purchase Return / Stock Adjustment.'));
        }

        return [$docId, $result['stock_warning'] ?? null];
    }

    // Bangun + posting SATU dokumen Stock In RM dari baris PV yang is_stock=1
    // & c_qty_accepted>0 (baris jasa/non-stock dilewati). Lokasi rak per baris:
    // (1) cocokkan i_location_code (teks bebas, PV-05) ke wh_loc.location_code
    // di gudang yang sama, (2) fallback mat_raw.def_location_id kalau ada &
    // satu gudang, (3) fallback lokasi usable+aktif pertama di gudang tsb.
    // Tanpa lokasi sama sekali = gudang belum lengkap di Master Warehouse,
    // dilempar exception (bukan ditebak location_id sembarang -- itu akan
    // merusak akurasi kartu stok fisik). Return null kalau tak ada baris stok
    // (mis. PV isinya jasa semua) -- rm_stock_doc_id tetap NULL, itu valid.
    // Kembalian: [id dokumen stok | null, peringatan nilai stok | null].
    protected static function postToInventory(PvReceipt $pv, string $login): array
    {
        $pv->loadMissing('lines');

        $stockLines = $pv->lines->filter(fn ($l) => $l->is_stock && $l->item_id && (float) $l->c_qty_accepted > 0);

        if ($stockLines->isEmpty()) {
            return [null, null];
        }

        $warehouse = WhWarehouse::where('warehouse_code', $pv->i_warehouse_code)->first();
        if (! $warehouse) {
            throw new \RuntimeException(tf("Warehouse code ':code' is not found in Master Warehouse.", ['code' => $pv->i_warehouse_code]));
        }

        $usableLocations = WhLoc::where('warehouse_id', $warehouse->id_warehouse)
            ->where('activate', true)->where('is_usable', true)
            ->orderBy('row_code')->orderBy('col_code')->orderBy('stack_code')->get();
        $fallbackLocation = $usableLocations->first();

        $lines = [];
        foreach ($stockLines as $line) {
            $location = null;

            if ($line->i_location_code) {
                $location = $usableLocations->firstWhere('location_code', trim($line->i_location_code));
            }

            if (! $location) {
                $defLocationId = MatRaw::where('material_id', $line->item_id)->value('def_location_id');
                if ($defLocationId) {
                    $location = $usableLocations->firstWhere('location_id', $defLocationId);
                }
            }

            $location ??= $fallbackLocation;

            if (! $location) {
                throw new \RuntimeException(tf("Warehouse ':code' has no active location/rack yet -- complete Master Warehouse first.", ['code' => $pv->i_warehouse_code]));
            }

            $lines[] = [
                'material_id' => $line->item_id,
                'qty' => (float) $line->c_qty_accepted,
                'uom_code' => $line->i_uom_stock,
                'unit_price' => (float) $line->c_unit_price,
                'lot_no' => $line->i_lot_no,
                'location_id' => $location->location_id,
            ];
        }

        $docService = app(StockDocumentService::class);

        $docId = $docService->saveDraft('RM', null, [
            'doc_no' => $docService->nextDocNo('RM', 'IN'),
            'doc_date' => $pv->i_doc_date,
            'doc_type' => 1,
            'doc_cat' => 'STOCK',
            'ref_doc_type' => 'PV',
            'ref_doc_id' => $pv->pv_id,
            'ref_doc_no' => $pv->doc_no,
            'warehouse_id' => $warehouse->id_warehouse,
            'notes' => "Auto dari Penerimaan Barang {$pv->doc_no}",
        ], $lines, $login);

        $result = app(StockPostingService::class)->postDocument('RM', $docId, $login);

        if (! $result['posted']) {
            throw new \RuntimeException($result['error'] ?? t('Failed to post the stock (unknown).'));
        }

        return [$docId, $result['stock_warning'] ?? null];
    }

    // Dr Persediaan / Cr GR-IR Clearing per baris is_stock=1 yang c_qty_accepted>0,
    // dikelompokkan per pasangan akun supaya voucher-nya ringkas. Sengaja di LUAR
    // transaksi tm2pur di atas -- fin_voucher/fin_voucher_line hidup di koneksi
    // tm2acc yang berbeda, JournalPostingService buka transaksinya sendiri.
    // Kegagalan/ketidaklengkapan akun TIDAK membatalkan posting PV yang sudah
    // terjadi -- cuma dikembalikan sbg pesan peringatan ke caller.
    protected static function postPurchaseJournal(PvReceipt $pv, string $login): ?string
    {
        $pv->load('lines');
        $rows = [];
        $unmapped = 0;

        foreach ($pv->lines as $line) {
            $qty = (float) $line->c_qty_accepted;
            if (! $line->is_stock || $qty <= 0 || ! $line->item_id) {
                continue;
            }

            $material = MatRaw::find($line->item_id);
            $prefix1 = $material ? MatCategory::level(1)->where('category_name', $material->category1)->first() : null;

            $stockAccountId = app(JournalPostingService::class)->resolveAccount($material?->stock_asset_account_id, $prefix1?->default_stock_asset_account_id);
            $grIrAccountId = app(JournalPostingService::class)->resolveAccount($material?->gr_ir_account_id, $prefix1?->default_gr_ir_account_id);
            $value = round($qty * (float) $line->c_unit_price, 2);

            if (! $stockAccountId || ! $grIrAccountId || $value <= 0) {
                $unmapped++;

                continue;
            }

            $key = $stockAccountId.'-'.$grIrAccountId;
            $rows[$key]['stock_account_id'] = $stockAccountId;
            $rows[$key]['gr_ir_account_id'] = $grIrAccountId;
            $rows[$key]['value'] = ($rows[$key]['value'] ?? 0) + $value;
        }

        if (! empty($rows)) {
            $lines = [];
            foreach ($rows as $row) {
                $lines[] = ['account_id' => $row['stock_account_id'], 'debit' => $row['value'], 'narration' => "Penerimaan barang {$pv->doc_no}"];
                $lines[] = ['account_id' => $row['gr_ir_account_id'], 'credit' => $row['value'], 'narration' => "GR/IR {$pv->doc_no} (belum ditagih supplier)"];
            }

            app(JournalPostingService::class)->post('PUR', 'PV', $pv->pv_id, "Penerimaan Barang {$pv->doc_no}", $lines, $login, $pv->i_doc_date?->toDateString());
        }

        return $unmapped > 0
            ? tf(':n lines have no Inventory/GR-IR account yet (item or category) -- the purchase journal is not complete for those lines.', ['n' => $unmapped])
            : null;
    }

    // KURANG KIRIM penerimaan supplier (v2.01, ShortageRequestService): jurnal koreksi Dr GR/IR / Cr Persediaan sebesar qty kurang x harga
    // penerimaan (pola postPurchaseJournal, sumber PV_SHORT seperti kekurangan subkon). null = terjurnal, teks = peringatan akun belum lengkap.
    public static function postShortageJournal(PvReceipt $pv, \App\Models\Pur\PvLine $line, float $stockQty, string $login): ?string
    {
        $material = MatRaw::find($line->item_id);
        $prefix1 = $material ? MatCategory::level(1)->where('category_name', $material->category1)->first() : null;
        $stockAccountId = app(JournalPostingService::class)->resolveAccount($material?->stock_asset_account_id, $prefix1?->default_stock_asset_account_id);
        $grIrAccountId = app(JournalPostingService::class)->resolveAccount($material?->gr_ir_account_id, $prefix1?->default_gr_ir_account_id);
        $value = round($stockQty * (float) $line->c_unit_price, 2);
        if (! $stockAccountId || ! $grIrAccountId || $value <= 0) {
            return tf('Line :line has no Inventory/GR-IR account yet (item or category) -- the shortage journal was not posted.', ['line' => $line->line_no]);
        }
        app(JournalPostingService::class)->post('PUR', 'PV_SHORT', $pv->pv_id, "Kurang kirim {$pv->doc_no} baris {$line->line_no}", [
            ['account_id' => $grIrAccountId, 'debit' => $value, 'narration' => "GR/IR {$pv->doc_no} (kurang kirim)"],
            ['account_id' => $stockAccountId, 'credit' => $value, 'narration' => "Koreksi persediaan kurang kirim {$pv->doc_no}"],
        ], $login, now()->toDateString());

        return null;
    }

    // KURANG KIRIM: stok ikut turun lewat dokumen Stock Out RM ber-ref PV_SHORT (jalur sah StockPostingService). Saldo yang tidak cukup
    // (bahan sudah keluar ke WO) membuat posting ditolak: dikembalikan sebagai peringatan, koreksinya lewat Stock Adjustment. null = beres.
    public static function correctStockForShortage(PvReceipt $pv, \App\Models\Pur\PvLine $line, float $stockQty, string $login): ?string
    {
        $in = $pv->rm_stock_doc_id ? RmStock::with('lines')->find($pv->rm_stock_doc_id) : null;
        $source = $in?->lines->firstWhere('material_id', $line->item_id);
        if (! $in || ! $in->stat_confirm || ! $source) {
            return t('No stock document was found for this receipt line -- correct the stock with a Stock Adjustment.');
        }
        $docService = app(StockDocumentService::class);
        $docId = $docService->saveDraft('RM', null, [
            'doc_no' => $docService->nextDocNo('RM', 'OUT'), 'doc_date' => now()->toDateString(), 'doc_type' => -1, 'doc_cat' => 'STOCK', 'ref_doc_type' => 'PV_SHORT',
            'ref_doc_id' => $pv->pv_id, 'ref_doc_no' => $pv->doc_no, 'warehouse_id' => $in->warehouse_id, 'notes' => "Koreksi kurang kirim Penerimaan Barang {$pv->doc_no} baris {$line->line_no}",
        ], [['material_id' => $source->material_id, 'qty' => $stockQty, 'uom_code' => $source->uom_code, 'unit_price' => (float) $source->unit_price, 'lot_no' => $source->lot_no, 'location_id' => $source->location_id]], $login);
        $result = app(StockPostingService::class)->postDocument('RM', $docId, $login);
        if (! $result['posted']) {
            $docService->cancel('RM', $docId, $login);

            return tf('The stock was not reduced (:error) -- correct it with a Stock Adjustment.', ['error' => $result['error'] ?? t('unknown')]);
        }

        return null;
    }

    public static function recalcPo(int $poId): void
    {
        $po = PoOrder::with('lines')->find($poId);

        if (! $po) {
            return;
        }

        foreach ($po->lines as $line) {
            $received = (float) DB::connection('tm2pur')->table('pv_line')
                ->join('pv_receipt', 'pv_receipt.pv_id', '=', 'pv_line.pv_id')
                ->where('pv_line.po_line_id', $line->po_line_id)
                ->where('pv_receipt.status', 'POSTED')
                ->sum('pv_line.i_qty_purchase');

            $outstanding = max((float) $line->i_qty - $received, 0);
            // Toleransi per item (mat_raw.tol_purchase, disalin ke po_line) -> kosong = Settings > Constants RECEIVE_TOLERANCE_PCT.
            $tolPct = (float) ($line->c_tol_pct ?? SysConstant::number('RECEIVE_TOLERANCE_PCT', 5));
            $variancePct = $line->i_qty > 0 ? ($outstanding / $line->i_qty * 100) : 0;

            // Baris yang ditutup di qty terima karena pemasok menolak / lewat jendela kirim kekurangan (ShortageRequestService) tetap tertutup.
            $status = match (true) {
                $line->c_line_status === ShortageRequestService::LINE_CLOSED => ShortageRequestService::LINE_CLOSED,
                $received >= (float) $line->i_qty => 'RECEIVED',
                $variancePct <= $tolPct && $received > 0 => 'CLOSED_BY_TOLERANCE',
                $received > 0 => 'PARTIALLY_RECEIVED',
                default => 'OPEN',
            };

            $line->update(['c_qty_received' => $received, 'c_qty_outstanding' => $outstanding, 'c_line_status' => $status]);
        }

        $receivableLines = $po->lines->where('is_receivable', true);
        $allClosed = $receivableLines->every(fn ($l) => in_array($l->fresh()->c_line_status, ['RECEIVED', 'CLOSED_BY_TOLERANCE', ShortageRequestService::LINE_CLOSED]));
        $anyReceived = $receivableLines->sum(fn ($l) => (float) $l->fresh()->c_qty_received) > 0;

        $newStatus = match (true) {
            $allClosed => 'RECEIVED',
            $anyReceived => 'PARTIALLY_RECEIVED',
            // Semua penerimaan PO ini di-Cancel -> kembali ke SENT (siap diterima lagi).
            in_array($po->status, ['RECEIVED', 'PARTIALLY_RECEIVED'], true) => 'SENT',
            default => $po->status,
        };

        if ($newStatus !== $po->status) {
            $po->update(['status' => $newStatus]);
        }
    }
}
