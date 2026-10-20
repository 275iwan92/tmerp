<?php

/* REMARK
 * Tujuan       : Header dokumen stok barang jadi (FG) (Stock In/Out/Adjustment/Opname/Return, dibedakan doc_cat + doc_type); aturan umum ada di StockDocHeader.
 * Sumber hulu  : tm2inv.fg_stock.
 * Input        : atribut header (kolom khas: product_id).
 * Output       : model Eloquent dengan lines() (FgStockD).
 * Dipakai oleh : layanan inventori, grid dan form dokumen stok.
 * Status       : Aktif.
 */

namespace App\Models\Inventory;

class FgStock extends StockDocHeader
{
    protected const EXTRA_KEYS = ['product_id'];

    protected const LINE_MODEL = FgStockD::class;

    protected $table = 'fg_stock';
}
