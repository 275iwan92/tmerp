<?php

/* REMARK
 * Tujuan       : Baris dokumen stok barang setengah jadi (SFG), termasuk qty_returned, return_condition, source_detail_id dan line_note/cancel_reason bila ada; aturan umum ada di StockDocLine.
 * Sumber hulu  : tm2inv.sfg_stock_d, ditulis StockDocumentService, StockDocEntryService.
 * Input        : kolom baris (kunci barang: parts_id, product_id, line_note, cancel_reason).
 * Output       : model Eloquent dengan relasi part().
 * Dipakai oleh : StockPostingService, StockReversalService, grid dan form dokumen stok.
 * Status       : Aktif.
 */

namespace App\Models\Inventory;

use App\Models\Master\ProdPart;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SfgStockD extends StockDocLine
{
    protected const ITEM_KEYS = ['parts_id', 'product_id', 'line_note', 'cancel_reason'];

    protected $table = 'sfg_stock_d';

    public function part(): BelongsTo
    {
        return $this->belongsTo(ProdPart::class, 'parts_id', 'id_part');
    }
}
