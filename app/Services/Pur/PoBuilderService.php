<?php

namespace App\Services\Pur;

use App\Models\Master\DocRmprefix1;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrLineAlloc;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Alokasi (pr_line_alloc) -> po_line, 1:1 dijaga UNIQUE uq_po_alloc (PROC_TIER_3
// §3.1). Konsolidasi lintas PR terjadi di HEADER po_order (banyak alokasi dari
// PR berbeda -> satu PO), bukan di baris -- baris tetap granular per sumber.
class PoBuilderService
{
    public static function findOrCreateDraft(int $supplierId): PoOrder
    {
        $draft = PoOrder::where('i_supplier_id', $supplierId)->where('status', 'DRAFT')->latest('po_id')->first();

        if ($draft) {
            return $draft;
        }

        return PoOrder::create([
            'i_doc_date' => now()->toDateString(),
            'i_supplier_id' => $supplierId,
            'i_po_type' => 'LOCAL',
            'i_procure_type' => 'REPEAT',
            'i_fx_rate' => 1,
            'status' => 'DRAFT',
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);
    }

    // Idempotent -- kalau alokasi ini sudah jadi po_line di PO ini, tidak dobel.
    public static function addAllocation(PoOrder $po, PrLineAlloc $alloc): ?PoLine
    {
        if ($alloc->c_is_converted) {
            return null;
        }

        return DB::connection('tm2pur')->transaction(function () use ($po, $alloc) {
            $alloc = PrLineAlloc::lockForUpdate()->find($alloc->alloc_id);

            if (! $alloc || $alloc->c_is_converted) {
                return null;
            }

            $line = $alloc->line()->with('material')->first();
            $login = Auth::user()->login;

            $lineClass = 'RAW';

            if ($line->i_item_source === 'FREE_TEXT') {
                $lineClass = $line->is_stock ? 'RAW' : 'INDIRECT';
            } elseif ($line->material) {
                $prefix1 = DocRmprefix1::where('category1', $line->material->category1)->first();
                $lineClass = $prefix1?->stock_class ?? 'RAW';
            }

            $nextSeq = ((int) $po->lines()->max('line_no')) + 1;

            $poLine = PoLine::create([
                'po_id' => $po->po_id,
                'line_no' => $nextSeq,
                'alloc_id' => $alloc->alloc_id,
                'item_id' => $line->item_id,
                'c_item_code' => $line->c_item_code,
                'i_description' => $line->i_description,
                'i_line_class' => $lineClass,
                'i_qty' => $alloc->i_qty,
                'i_uom' => $line->i_uom,
                'c_uom_stock' => $line->material?->uom_stock,
                'c_conv_to_stock' => $line->material?->conversion_to_stock,
                'i_price' => $line->i_est_price ?? 0,
                'i_disc_pct' => 0,
                'i_department_id' => $line->i_department_id,
                'i_coa_account_id' => $line->i_coa_account_id,
                'i_co_line_id' => $line->i_co_line_id,
                'is_stock' => $lineClass !== 'SERVICE',
                'c_tol_pct' => $line->material?->tol_purchase,
                'created_by' => $login,
                'updated_by' => $login,
            ]);
            $poLine->recalcNet();
            $poLine->save();

            $alloc->update(['c_is_converted' => true, 'i_supplier_id' => $po->i_supplier_id, 'updated_by' => $login]);

            $qtyOrdered = (float) $line->c_qty_ordered + (float) $alloc->i_qty;
            $line->update([
                'c_qty_ordered' => $qtyOrdered,
                'c_line_status' => $qtyOrdered >= (float) $line->i_qty ? 'FULLY_ORDERED' : 'PARTIALLY_ORDERED',
            ]);

            return $poLine;
        });
    }
}
