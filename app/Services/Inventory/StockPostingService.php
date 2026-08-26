<?php

namespace App\Services\Inventory;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\SfgStock;
use App\Models\Inventory\SfgStockcard;
use App\Models\Master\DocRmprefix1;
use App\Models\Master\MRawMaterial;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Services\Acc\JournalPostingService;
use Illuminate\Support\Facades\DB;

// Port dari trigger legacy trg_rm_stock_confirm/trg_fg_stock_confirm/
// trg_sfg_stock_confirm (database/legacy/tminv_20260722_2018_sd.sql baris
// ~1278/1347/1430) yang TIDAK ikut ter-import ke DB live (SHOW TRIGGERS
// kosong) -- lihat docs/sidebarmenustandard.md #6a utk alasan kenapa
// logikanya diport ke sini (App\Services\*, bukan trigger SQL, konsisten
// dgn Costing/Quotation/Flow Engine). Satu-satunya jalur SAH utk menulis
// *_stockcard -- Livewire component TIDAK BOLEH insert langsung (INV_TIER_4 #7).
// AUTO-JURNAL PEMAKAIAN (2026-08-22, riset ERP Odoo/SAP/D365/Oracle; diperluas
// ke SFG 2026-08-22 sore): Stock Out RM/SFG sungguhan (docType OUT) otomatis
// posting Dr HPP (cogs_account_id) / Cr Persediaan (stock_asset_account_id) ke
// journal_code 'USG'. HANYA docType OUT -- Adjustment/Opname/Return/BB SENGAJA
// belum dijurnal (beda makna akuntansi, bukan konsumsi produksi). FG TETAP
// tidak lewat Stock Out sama sekali (lihat komentar stock-out.blade.php) jadi
// tidak relevan di sini. Akun RM: item override (m_raw_material) -> fallback
// kategori (doc_rmprefix1). Akun SFG: item override (prod_part) SAJA -- tidak
// ada tabel fallback kategori setara doc_rmprefix1 utk SFG, jadi part yang
// belum diisi akunnya akan selalu dilewati+peringatan sampai diisi manual.
// Lihat JournalPostingService & PvPostingService::postPurchaseJournal() utk
// pola yg sama di sisi pembelian.
class StockPostingService
{
    private const CATEGORY_MAP = [
        'RM' => ['header' => RmStock::class, 'card' => RmStockcard::class, 'itemKey' => 'material_id', 'itemModel' => MRawMaterial::class, 'itemPk' => 'material_id'],
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

    /**
     * Posting dokumen: hitung saldo berjalan per item+lokasi, insert baris
     * stockcard, kunci header jadi POSTED. Return ['posted' => bool, 'error' => ?string].
     */
    public function postDocument(string $category, int $docId, string $actingUser): array
    {
        $map = self::CATEGORY_MAP[$category] ?? null;
        abort_if(! $map, 404, "Kelas stok tidak dikenal: {$category}");

        // Diisi di dalam transaksi (butuh $prevAvg per baris, cuma tersedia di
        // situ), dipakai SETELAH commit -- fin_voucher/fin_voucher_line hidup di
        // koneksi tm2acc yang beda, jangan disatukan ke transaksi tm2inv di atas.
        $usageJournalRows = [];

        $result = DB::connection('tm2inv')->transaction(function () use ($map, $category, $docId, $actingUser, &$usageJournalRows) {
            /** @var \App\Models\Inventory\RmStock|\App\Models\Inventory\FgStock|\App\Models\Inventory\SfgStock $header */
            $header = $map['header']::with('lines')->lockForUpdate()->findOrFail($docId);

            if ($header->stat_confirm) {
                return ['posted' => false, 'error' => 'Dokumen sudah pernah di-posting sebelumnya.'];
            }
            if ($header->stat_cancel) {
                return ['posted' => false, 'error' => 'Dokumen sudah dibatalkan, tidak bisa diposting.'];
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

                $prev = $cardModel::where($itemKey, $itemId)
                    ->where('location_id', $locationId)
                    ->where(function ($q) use ($header) {
                        $q->where('doc_date', '<', $header->doc_date)
                            ->orWhere(function ($q2) use ($header) {
                                $q2->where('doc_date', $header->doc_date)->where('doc_id', '<', $header->getKey());
                            });
                    })
                    ->orderByDesc('doc_date')->orderByDesc('doc_id')
                    ->lockForUpdate()
                    ->first();

                $qtyBb = $prev ? (float) $prev->qty_eb : 0.0;
                $qtyEb = $qtyBb + $qtyFinal;

                if ($qtyEb < 0 && ! in_array($cat, ['AD', 'OP'], true)) {
                    return ['posted' => false, 'error' => "Saldo item #{$itemId} di lokasi #{$locationId} akan negatif ({$qtyEb}) -- posting dibatalkan."];
                }

                $docType = match (true) {
                    $cat === 'BB' => 'BB',
                    $cat === 'RETURN' => 'IN',
                    in_array($cat, ['AD', 'OP'], true) => 'AD',
                    (int) $header->doc_type === 1 => 'IN',
                    default => 'OUT',
                };

                $prevAvg = $prev ? (float) $prev->avg_unitprice : 0.0;

                // Auto-jurnal pemakaian (2026-08-22, diperluas ke SFG 2026-08-22):
                // cuma Stock Out sungguhan (docType OUT, bukan BB/RETURN/AD/OP) yang
                // masuk HPP -- Adjustment/Opname punya makna akuntansi beda (koreksi,
                // bukan konsumsi produksi) & sengaja TIDAK dijurnal otomatis di sini.
                // SFG pakai akun langsung dari prod_part (stock_asset_account_id/
                // cogs_account_id, migration 2026_08_22_000022) -- TANPA fallback
                // kategori (prod_part tidak punya tabel setara doc_rmprefix1), jadi
                // kalau belum diisi manual per part, baris ini akan selalu dilewati +
                // masuk peringatan "belum lengkap" -- bukan silent skip.
                // Nilai pakai avg_unitprice SEBELUM baris ini (prevAvg) -- itu
                // valuasi moving-average yang sah saat barang ini keluar.
                if (in_array($category, ['RM', 'SFG'], true) && $docType === 'OUT') {
                    $usageJournalRows[] = ['category' => $category, 'item_id' => $itemId, 'qty' => abs($qtyFinal), 'unit_cost' => $prevAvg];
                }

                $unitPrice = (float) ($line->unit_price ?? 0);
                $qtyIn = ($cat !== 'AD' && $cat !== 'OP' && ((int) $header->doc_type === 1 || $cat === 'RETURN')) ? abs((float) $line->qty) : 0;
                $qtyOut = ($cat !== 'AD' && $cat !== 'OP' && (int) $header->doc_type === -1 && $cat !== 'RETURN') ? abs((float) $line->qty) : 0;
                $qtyAdj = in_array($cat, ['AD', 'OP'], true) ? $qtyFinal : 0;

                // Valuasi AVG bergerak (moving average) -- FIFO/LIFO (inv_stock_layer) belum
                // diimplementasikan di v1, costing_method per-item belum final (inventory_state.yaml).
                $newAvg = $qtyIn > 0
                    ? (($qtyBb * $prevAvg) + ($qtyIn * $unitPrice)) / max($qtyBb + $qtyIn, 0.0001)
                    : $prevAvg;

                $cardModel::create([
                    'previous_doc_id' => $prev?->getKey(),
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
                    'qty_bb' => $qtyBb,
                    'qty_in' => $qtyIn,
                    'qty_out' => $qtyOut,
                    'qty_adj' => $qtyAdj,
                    'qty_eb' => $qtyEb,
                    'avg_unitprice' => $newAvg,
                    'value_eb' => $qtyEb * $newAvg,
                    'period_id' => $header->period_id,
                    'create_by' => $actingUser,
                    'update_by' => $actingUser,
                ]);

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
            // m_raw_material.cur_stock legacy masih NULL (5893 baris blm pernah disentuh
            // stok), jadi wajib COALESCE, bukan increment() polos.
            foreach ($netByItem as $itemId => $delta) {
                $map['itemModel']::where($map['itemPk'], $itemId)
                    ->update(['cur_stock' => DB::raw('COALESCE(cur_stock, 0) + ('.(float) $delta.')')]);
            }

            return ['posted' => true, 'error' => null];
        });

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
                $cogsAccountId = $part?->cogs_account_id;
                $stockAccountId = $part?->stock_asset_account_id;
            } else {
                $material = MRawMaterial::find($r['item_id']);
                $prefix1 = $material ? DocRmprefix1::where('category1', $material->category1)->first() : null;
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
            ? "{$unmapped} baris belum punya akun HPP/Persediaan (item maupun kategori) -- jurnal pemakaian belum lengkap untuk baris tsb."
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
