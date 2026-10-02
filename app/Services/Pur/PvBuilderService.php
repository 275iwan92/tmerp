<?php

namespace App\Services\Pur;

use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// purchasereceive_delta_multipo.md -- alur supplier-first: pilih supplier (§ Langkah 1,
// menggantikan v1_pv_supplier_worklist yg tak dibuat sbg VIEW fisik di sini,
// diimplementasi sbg query langsung spt pola PoBuilderService::addAllocation)
// -> worklist baris PO terbuka lintas-PO (§ Langkah 2, v1_po_outstanding) ->
// buat SPB (1 SPB : N PO, PV-01..PV-06).
class PvBuilderService
{
    // Langkah 1: supplier yang punya minimal satu baris PO terbuka & receivable.
    public static function supplierWorklist()
    {
        return PoOrder::whereIn('status', ['SENT', 'PARTIALLY_RECEIVED'])
            ->where('stat_cancel', false)
            ->with(['lines' => fn ($q) => $q->where('is_receivable', true)->whereColumn('c_qty_received', '<', 'i_qty')])
            ->get()
            ->filter(fn ($po) => $po->lines->isNotEmpty())
            ->groupBy('i_supplier_id')
            ->map(function ($pos, $supplierId) {
                $allLines = $pos->flatMap->lines;

                return (object) [
                    'i_supplier_id' => $supplierId,
                    'open_po_count' => $pos->count(),
                    'open_line_count' => $allLines->count(),
                    'oldest_po_date' => $pos->min('i_doc_date'),
                    'earliest_delivery_date' => $pos->min('i_delivery_date'),
                ];
            })
            ->values();
    }

    // Langkah 2: baris PO terbuka milik satu supplier (worklist, v1_po_outstanding).
    public static function openLinesForSupplier(int $supplierId)
    {
        return PoLine::with('order')
            ->whereHas('order', fn ($q) => $q->where('i_supplier_id', $supplierId)
                ->whereIn('status', ['SENT', 'PARTIALLY_RECEIVED'])
                ->where('stat_cancel', false))
            ->where('is_receivable', true)
            ->whereColumn('c_qty_received', '<', 'i_qty')
            ->get()
            ->sortBy(['c_po_doc_no' => 0, 'line_no' => 0]);
    }

    // PV-02/03/04 di-cek di sini krn worklist Langkah 2 sudah difilter per-supplier
    // (PV-02 otomatis terpenuhi); PV-03/04 tetap dicek eksplisit -- semua PO yg
    // baris-nya dipilih harus mata uang sama (fase ini seluruhnya IDR).
    public static function createFromLines(int $supplierId, string $warehouseCode, array $poLineIds): PvReceipt
    {
        $lines = PoLine::with('order')->whereIn('po_line_id', $poLineIds)->get();

        if ($lines->isEmpty()) {
            throw new \InvalidArgumentException(t('No line is selected.'));
        }

        if ($lines->pluck('order.i_supplier_id')->unique()->count() > 1 || $lines->first()->order->i_supplier_id !== $supplierId) {
            throw new \InvalidArgumentException(t('[PV-02] All lines must be from the same supplier.'));
        }

        if ($lines->pluck('order.i_currency_id')->unique()->count() > 1) {
            throw new \InvalidArgumentException(t('[PV-04] All POs must have the same currency.'));
        }

        $login = Auth::user()->login;

        return DB::connection('tm2pur')->transaction(function () use ($lines, $supplierId, $warehouseCode, $login) {
            $pv = PvReceipt::create([
                'doc_no' => null, // nomor SPB lahir saat diposting (PvPostingService::post)
                'i_doc_date' => now()->toDateString(),
                'i_supplier_id' => $supplierId,
                'i_warehouse_code' => $warehouseCode,
                'status' => 'DRAFT',
                'created_by' => $login,
                'updated_by' => $login,
            ]);

            static::appendLines($pv, $lines, $login);

            $pv->refreshPoSummary();
            $pv->save();

            return $pv;
        });
    }

    public static function addLines(PvReceipt $pv, array $poLineIds): void
    {
        if ($pv->status !== 'DRAFT') {
            return;
        }

        $existingPoLineIds = $pv->lines()->pluck('po_line_id')->all();
        $newIds = array_diff($poLineIds, $existingPoLineIds);

        if (empty($newIds)) {
            return;
        }

        $lines = PoLine::with('order')->whereIn('po_line_id', $newIds)->get();
        $login = Auth::user()->login;

        DB::connection('tm2pur')->transaction(function () use ($pv, $lines, $login) {
            static::appendLines($pv, $lines, $login);
            $pv->refreshPoSummary();
            $pv->save();
        });
    }

    protected static function appendLines(PvReceipt $pv, $lines, string $login): void
    {
        $nextSeq = ((int) $pv->lines()->max('line_no')) + 1;

        foreach ($lines as $line) {
            $qtyOutstanding = (float) $line->i_qty - (float) $line->c_qty_received;

            $pvLine = PvLine::create([
                'pv_id' => $pv->pv_id,
                'line_no' => $nextSeq++,
                'po_line_id' => $line->po_line_id,
                'c_po_id' => $line->po_id,
                'c_po_doc_no' => $line->order->doc_no,
                'c_po_line_no' => $line->line_no,
                'item_id' => $line->item_id,
                'c_item_code' => $line->c_item_code,
                'i_description' => $line->i_description,
                'is_stock' => $line->is_stock,
                'i_qty_purchase' => $qtyOutstanding,
                'i_uom_purchase' => $line->i_uom,
                'i_qty_stock' => $qtyOutstanding,
                'i_uom_stock' => $line->c_uom_stock ?: $line->i_uom,
                'c_conv_used' => $line->c_conv_to_stock,
                'c_tol_pct' => $line->c_tol_pct,
                'c_unit_price' => $line->i_price,
                'i_co_line_id' => $line->i_co_line_id,
                'created_by' => $login,
                'updated_by' => $login,
            ]);
            $pvLine->recalcVariance($qtyOutstanding);
            $pvLine->save();
        }
    }
}
