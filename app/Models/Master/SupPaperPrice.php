<?php

/*
 * REMARK
 * Tujuan       : Model harga kertas per supplier, berversi menurut tanggal berlaku (pola sama dengan FinExchangeRate).
 * Sumber hulu  : Layar Master Data > Supplier Paper Price (SupplierPaperPriceLookup).
 * Input        : Baris tm2mst.sup_paper_price.
 * Output       : Konstanta harga per supplier + jenis kertas + gramasi (opsional) + tanggal.
 * Dipakai oleh : PaperPriceEngine, SupplierPaperPriceLookup.
 * Status       : Aktif.
 */

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class SupPaperPrice extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sup_paper_price';

    protected $primaryKey = 'id_paper_price';

    protected $fillable = [
        'supplier_id', 'paper_kind', 'grammage_id', 'roll_price', 'roll_price_unit', 'cut_cost_per_kg',
        'sheet_price_per_kg', 'price_basis', 'effective_date', 'activate', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'roll_price' => 'decimal:4',
        'cut_cost_per_kg' => 'decimal:4',
        'sheet_price_per_kg' => 'decimal:4',
        'effective_date' => 'date',
        'activate' => 'boolean',
    ];
}
