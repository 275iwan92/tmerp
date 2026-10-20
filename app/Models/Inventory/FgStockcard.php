<?php

/* REMARK
 * Tujuan       : Baris buku stok (stockcard) barang jadi (FG): BB, In, Out, Adj, EB per barang + lokasi; semua aturan umum ada di StockcardRow.
 * Sumber hulu  : tm2inv.fg_stockcard, ditulis StockPostingService::postDocument().
 * Input        : atribut ledger (kunci barang: product_id, parts_id).
 * Output       : model Eloquent.
 * Dipakai oleh : layanan inventori (posting, reversal, valuasi, kartu stok), grid laporan stok.
 * Status       : Aktif.
 */

namespace App\Models\Inventory;

class FgStockcard extends StockcardRow
{
    protected const ITEM_KEYS = ['product_id', 'parts_id'];

    protected $table = 'fg_stockcard';
}
