<?php

/* REMARK
 * Tujuan       : Baris buku stok (stockcard) bahan baku (RM): BB, In, Out, Adj, EB per barang + lokasi; semua aturan umum ada di StockcardRow.
 * Sumber hulu  : tm2inv.rm_stockcard, ditulis StockPostingService::postDocument().
 * Input        : atribut ledger (kunci barang: material_id).
 * Output       : model Eloquent.
 * Dipakai oleh : layanan inventori (posting, reversal, valuasi, kartu stok), grid laporan stok.
 * Status       : Aktif.
 */

namespace App\Models\Inventory;

class RmStockcard extends StockcardRow
{
    protected const ITEM_KEYS = ['material_id'];

    protected $table = 'rm_stockcard';
}
