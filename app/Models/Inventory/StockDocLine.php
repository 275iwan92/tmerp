<?php

/* REMARK
 * Tujuan       : Dasar bersama baris dokumen stok RM / SFG / FG (qty, qty_returned, qty sistem/fisik, kondisi return, harga, lokasi, lot):
 *                koneksi tm2inv, kunci doc_detail_id, relasi lokasi. Turunan menyebut tabel, kunci barang, dan relasi barangnya.
 * Sumber hulu  : tm2inv.rm_stock_d / sfg_stock_d / fg_stock_d.
 * Input        : kolom baris dari StockDocumentService, StockDocEntryService.
 * Output       : model Eloquent dengan location().
 * Dipakai oleh : RmStockD, SfgStockD, FgStockD; StockPostingService, StockReversalService, grid dan form dokumen stok.
 * Status       : Aktif; menggantikan tiga salinan kelas yang sama (Jo 2026-10-20).
 */

namespace App\Models\Inventory;

use App\Models\Master\WhLoc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class StockDocLine extends Model
{
    const CREATED_AT = 'create_log';

    const UPDATED_AT = 'update_log';

    protected const CORE = [
        'doc_id', 'seq_no', 'qty', 'qty_returned',
        'qty_system', 'qty_physical', 'action_type', 'return_condition', 'source_detail_id',
        'uom_code', 'lot_no', 'unit_price', 'qty_unitprice', 'location_id',
        'batch_id', 'owner_type', 'owner_id',
        'create_by', 'update_by',
    ];

    /** Kolom khas kelas stok (material_id / product_id / parts_id, line_note, cancel_reason). */
    protected const ITEM_KEYS = [];

    protected $connection = 'tm2inv';

    protected $primaryKey = 'doc_detail_id';

    protected $casts = [
        'qty' => 'decimal:4',
        'qty_returned' => 'decimal:4',
        'qty_system' => 'decimal:4',
        'qty_physical' => 'decimal:4',
        'unit_price' => 'decimal:4',
    ];

    public function getFillable(): array
    {
        return array_merge(static::CORE, static::ITEM_KEYS);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WhLoc::class, 'location_id', 'location_id');
    }
}
