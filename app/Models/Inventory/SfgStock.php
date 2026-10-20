<?php

/* REMARK
 * Tujuan       : Header dokumen stok barang setengah jadi (SFG) (Stock In/Out/Adjustment/Opname/Return, dibedakan doc_cat + doc_type); aturan umum ada di StockDocHeader.
 * Sumber hulu  : tm2inv.sfg_stock.
 * Input        : atribut header (kolom khas: product_id, wo_id).
 * Output       : model Eloquent dengan lines() (SfgStockD).
 * Dipakai oleh : layanan inventori, grid dan form dokumen stok.
 * Status       : Aktif.
 */

namespace App\Models\Inventory;

class SfgStock extends StockDocHeader
{
    protected const EXTRA_KEYS = ['product_id', 'wo_id'];

    protected const LINE_MODEL = SfgStockD::class;

    protected $table = 'sfg_stock';
}
