<?php

/* REMARK
 * Tujuan       : Baris dokumen stok bahan baku (RM), termasuk qty_returned, return_condition, source_detail_id dan line_note/cancel_reason bila ada; aturan umum ada di StockDocLine.
 * Sumber hulu  : tm2inv.rm_stock_d, ditulis StockDocumentService, StockDocEntryService.
 * Input        : kolom baris (kunci barang: material_id, line_note).
 * Output       : model Eloquent dengan relasi material().
 * Dipakai oleh : StockPostingService, StockReversalService, grid dan form dokumen stok.
 * Status       : Aktif.
 */

namespace App\Models\Inventory;

use App\Models\Master\MatRaw;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RmStockD extends StockDocLine
{
    protected const ITEM_KEYS = ['material_id', 'line_note'];

    protected $table = 'rm_stock_d';

    public function material(): BelongsTo
    {
        return $this->belongsTo(MatRaw::class, 'material_id', 'material_id');
    }
}
