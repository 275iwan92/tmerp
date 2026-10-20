<?php

/* REMARK
 * Tujuan       : Baris buku stok (stockcard) barang setengah jadi (SFG): BB, In, Out, Adj, EB per barang + lokasi; semua aturan umum ada di StockcardRow.
 * Sumber hulu  : tm2inv.sfg_stockcard, ditulis StockPostingService::postDocument().
 * Input        : atribut ledger (kunci barang: parts_id, product_id).
 * Output       : model Eloquent.
 * Dipakai oleh : layanan inventori (posting, reversal, valuasi, kartu stok), grid laporan stok.
 * Status       : Aktif.
 */

namespace App\Models\Inventory;

class SfgStockcard extends StockcardRow
{
    protected const ITEM_KEYS = ['parts_id', 'product_id'];

    protected $table = 'sfg_stockcard';
}
