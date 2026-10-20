<?php

/* REMARK
 * Tujuan       : Baris dokumen stok barang jadi (FG), termasuk qty_returned, return_condition, source_detail_id dan line_note/cancel_reason bila ada; aturan umum ada di StockDocLine.
 * Sumber hulu  : tm2inv.fg_stock_d, ditulis StockDocumentService, StockDocEntryService.
 * Input        : kolom baris (kunci barang: product_id, parts_id).
 * Output       : model Eloquent dengan relasi product().
 * Dipakai oleh : StockPostingService, StockReversalService, grid dan form dokumen stok.
 * Status       : Aktif.
 */

namespace App\Models\Inventory;

use App\Models\Master\ProdProduct;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FgStockD extends StockDocLine
{
    protected const ITEM_KEYS = ['product_id', 'parts_id'];

    protected $table = 'fg_stock_d';

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }
}
