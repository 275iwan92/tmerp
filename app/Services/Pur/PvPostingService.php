<?php

namespace App\Services\Pur;

use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PvReceipt;
use App\Services\Acc\JournalPostingService;
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
                $blockers[] = "Baris {$line->line_no} belum di-QC qty.";

                continue;
            }

            if ($line->qc_quality_status === 'PENDING') {
                $blockers[] = "Baris {$line->line_no} belum di-QC kualitas.";

                continue;
            }

            if ($line->is_tol_exceeded && ! $line->tol_override_by) {
                $blockers[] = "Baris {$line->line_no} melampaui toleransi, belum di-override.";
            }
        }

        return $blockers;
    }

    public static function post(PvReceipt $pv): array
    {
        if ($pv->status !== 'QC_PENDING' && $pv->status !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Dokumen sudah tidak berstatus DRAFT/QC_PENDING.'];
        }

        $blockers = static::blockers($pv);

        if (! empty($blockers)) {
            return ['ok' => false, 'message' => implode(' ', $blockers)];
        }

        $login = Auth::user()->login;

        try {
            $stockDocId = static::postToInventory($pv, $login);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal posting stok fisik: '.$e->getMessage()];
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

        return ['ok' => true, 'message' => null, 'journal_warning' => $journalWarning];
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
    protected static function postToInventory(PvReceipt $pv, string $login): ?int
    {
        $pv->loadMissing('lines');

        $stockLines = $pv->lines->filter(fn ($l) => $l->is_stock && $l->item_id && (float) $l->c_qty_accepted > 0);

        if ($stockLines->isEmpty()) {
            return null;
        }

        $warehouse = WhWarehouse::where('warehouse_code', $pv->i_warehouse_code)->first();
        if (! $warehouse) {
            throw new \RuntimeException("Kode gudang '{$pv->i_warehouse_code}' tidak ditemukan di Master Warehouse.");
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
                throw new \RuntimeException("Gudang '{$pv->i_warehouse_code}' belum punya lokasi/rak aktif -- lengkapi Master Warehouse dulu.");
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
            throw new \RuntimeException($result['error'] ?? 'Gagal posting stok (tidak diketahui).');
        }

        return $docId;
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
            ? "{$unmapped} baris belum punya akun Persediaan/GR-IR (item maupun kategori) -- jurnal pembelian belum lengkap untuk baris tsb."
            : null;
    }

    protected static function recalcPo(int $poId): void
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
            $tolPct = (float) ($line->c_tol_pct ?? 5);
            $variancePct = $line->i_qty > 0 ? ($outstanding / $line->i_qty * 100) : 0;

            $status = match (true) {
                $received >= (float) $line->i_qty => 'RECEIVED',
                $variancePct <= $tolPct && $received > 0 => 'CLOSED_BY_TOLERANCE',
                $received > 0 => 'PARTIALLY_RECEIVED',
                default => 'OPEN',
            };

            $line->update(['c_qty_received' => $received, 'c_qty_outstanding' => $outstanding, 'c_line_status' => $status]);
        }

        $receivableLines = $po->lines->where('is_receivable', true);
        $allClosed = $receivableLines->every(fn ($l) => in_array($l->fresh()->c_line_status, ['RECEIVED', 'CLOSED_BY_TOLERANCE']));
        $anyReceived = $receivableLines->sum(fn ($l) => (float) $l->fresh()->c_qty_received) > 0;

        $newStatus = match (true) {
            $allClosed => 'RECEIVED',
            $anyReceived => 'PARTIALLY_RECEIVED',
            default => $po->status,
        };

        if ($newStatus !== $po->status) {
            $po->update(['status' => $newStatus]);
        }
    }
}
