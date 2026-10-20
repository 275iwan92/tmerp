<?php

/* REMARK
 * Tujuan       : Header dokumen stok bahan baku (RM) (Stock In/Out/Adjustment/Opname/Return, dibedakan doc_cat + doc_type); aturan umum ada di StockDocHeader.
 * Sumber hulu  : tm2inv.rm_stock.
 * Input        : atribut header (kolom khas: wo_id, wo_no).
 * Output       : model Eloquent dengan lines() (RmStockD).
 * Dipakai oleh : layanan inventori, grid dan form dokumen stok.
 * Status       : Aktif.
 */

namespace App\Models\Inventory;

class RmStock extends StockDocHeader
{
    protected const EXTRA_KEYS = ['wo_id', 'wo_no'];

    protected const LINE_MODEL = RmStockD::class;

    protected $table = 'rm_stock';
}
