<?php

/*
 * REMARK
 * Tujuan       : Model harga duplex per supplier, per rentang gsm, berversi menurut tanggal berlaku.
 * Sumber hulu  : Layar Master Data > Supplier Duplex Price (SupplierDuplexPriceLookup); Excel Jo sheet INDEX.
 * Input        : Baris tm2mst.sup_duplex_price.
 * Output       : Konstanta harga beli per supplier menurut tanggal berlaku (pola sama dengan FinExchangeRate).
 * Dipakai oleh : DuplexPriceEngine, layar lookup terkait.
 * Status       : Aktif.
 */

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class SupDuplexPrice extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sup_duplex_price';

    protected $primaryKey = 'id_duplex_price';

    protected $fillable = ['supplier_id', 'grade', 'gsm_from', 'gsm_to', 'formula', 'index_value', 'apkey_pct', 'price_basis', 'effective_date', 'activate', 'created_by', 'updated_by'];

    protected $casts = [
        'gsm_from' => 'decimal:2',
        'gsm_to' => 'decimal:2',
        'index_value' => 'decimal:4',
        'apkey_pct' => 'decimal:4',
        'effective_date' => 'date',
        'activate' => 'boolean',
    ];
}
