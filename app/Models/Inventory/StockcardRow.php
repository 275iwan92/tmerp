<?php

/* REMARK
 * Tujuan       : Dasar bersama baris buku stok (stockcard) RM / SFG / FG: koneksi tm2inv, kunci doc_id, kolom saldo (BB, In, Out, Adj, EB) dan nilai.
 *                Turunan hanya menyebut tabel dan kunci barangnya (ITEM_KEYS).
 * Sumber hulu  : tm2inv.rm_stockcard / sfg_stockcard / fg_stockcard.
 * Input        : atribut baris ledger dari StockPostingService / StockLedger.
 * Output       : model Eloquent dengan kolom boleh-isi CORE + ITEM_KEYS.
 * Dipakai oleh : RmStockcard, SfgStockcard, FgStockcard; layanan inventori (posting, reversal, valuasi).
 * Status       : Aktif; menggantikan tiga salinan kelas yang sama (Jo 2026-10-20).
 */

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Ledger hasil posting (StockPostingService::postDocument()) -- JANGAN pernah diinsert langsung dari komponen layar, cuma dari service.
// Unique (reference_name, reference_detail_id) mencegah double-post (INV-04).
abstract class StockcardRow extends Model
{
    const CREATED_AT = 'create_log';

    const UPDATED_AT = 'update_log';

    /** Kolom yang sama di ketiga kelas stok. */
    protected const CORE = [
        'previous_doc_id', 'doc_date', 'doc_type',
        'location_id', 'warehouse_id', 'lot_no', 'qty', 'uom_code',
        'reference_detail_id', 'reference_name',
        'qty_bb', 'qty_in', 'qty_out', 'qty_adj', 'qty_eb', 'post_date',
        'value_bb', 'value_in', 'value_out', 'value_adj', 'value_eb', 'avg_unitprice', 'period_id',
        'batch_id', 'owner_type', 'owner_id',
        'create_by', 'update_by',
    ];

    /** Kunci barang khas kelas stok (material_id / product_id / parts_id). */
    protected const ITEM_KEYS = [];

    protected $connection = 'tm2inv';

    protected $primaryKey = 'doc_id';

    protected $casts = [
        'doc_date' => 'datetime',
        'post_date' => 'date',
        'qty' => 'decimal:4',
        'qty_bb' => 'decimal:4',
        'qty_in' => 'decimal:4',
        'qty_out' => 'decimal:4',
        'qty_adj' => 'decimal:4',
        'qty_eb' => 'decimal:4',
    ];

    public function getFillable(): array
    {
        return array_merge(static::CORE, static::ITEM_KEYS);
    }
}
