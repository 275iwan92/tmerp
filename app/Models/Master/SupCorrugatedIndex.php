<?php

/*
 * REMARK
 * Tujuan       : Model index lembar gelombang per supplier dan substance (harga per m2 lembar trim), berversi menurut tanggal berlaku.
 * Sumber hulu  : Layar Master Data > Supplier Corrugated Index (SupplierCorrugatedIndexLookup); Excel Jo sheet UJK, RCA.
 * Input        : Baris tm2mst.sup_corrugated_index.
 * Output       : Konstanta harga beli per supplier menurut tanggal berlaku (pola sama dengan FinExchangeRate).
 * Dipakai oleh : CorrugatedSheetPriceEngine, layar lookup terkait.
 * Status       : Aktif.
 */

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class SupCorrugatedIndex extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sup_corrugated_index';

    protected $primaryKey = 'id_corr_index';

    protected $fillable = ['supplier_id', 'substance_name', 'index_per_m2', 'price_basis', 'effective_date', 'activate', 'created_by', 'updated_by'];

    protected $casts = [
        'index_per_m2' => 'decimal:4',
        'effective_date' => 'date',
        'activate' => 'boolean',
    ];
}
