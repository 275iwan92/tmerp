<?php

namespace App\Services\Inventory;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\SfgStock;
use App\Models\Inventory\SfgStockcard;
use App\Models\Master\MRawMaterial;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use Illuminate\Support\Facades\DB;

// Port dari trigger legacy trg_rm_stock_confirm/trg_fg_stock_confirm/
// trg_sfg_stock_confirm (database/legacy/tminv_20260722_2018_sd.sql baris
// ~1278/1347/1430) yang TIDAK ikut ter-import ke DB live (SHOW TRIGGERS
// kosong) -- lihat docs/SIDEBAR_MENU_STANDARD.md #6a utk alasan kenapa
// logikanya diport ke sini (App\Services\*, bukan trigger SQL, konsisten
// dgn Costing/Quotation/Flow Engine). Satu-satunya jalur SAH utk menulis
// *_stockcard -- Livewire component TIDAK BOLEH insert langsung (INV_TIER_4 #7).
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

        return DB::connection('tm2inv')->transaction(function () use ($map, $category, $docId, $actingUser) {
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
                $unitPrice = (float) ($line->unit_price ?? 0);
                $qtyIn = ($cat !== 'AD' && $cat !== 'OP' && ((int) $header->doc_type === 1 || $cat === 'RETURN')) ? abs((float) $line->qty) : 0;
                $qtyOut = ($cat !== 'AD' && $cat !== 'OP' && (int) $header->doc_type === -1 && $cat !== 'RETURN') ? abs((float) $line->qty) : 0;
                $qtyAdj = in_array($cat, ['AD', 'OP'], true) ? $qtyFinal : 0;

                // Valuasi AVG bergerak (moving average) -- FIFO/LIFO (inv_stock_layer) belum
                // diimplementasikan di v1, costing_method per-item belum final (INV_STATE.yaml).
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
