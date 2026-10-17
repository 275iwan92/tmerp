<?php

/*
 * REMARK
 * Tujuan       : Model harga Single Face per supplier, berversi menurut tanggal berlaku (pola sama dengan FinExchangeRate).
 * Sumber hulu  : Layar Master Data > Supplier Single Face Price (SupplierSingleFacePriceLookup).
 * Input        : Baris tm2mst.sup_single_face_price.
 * Output       : Index harga per m2 per supplier + substance + flute + tanggal.
 * Dipakai oleh : PaperPriceEngine (cabang Single Face), SupplierSingleFacePriceLookup.
 * Status       : Aktif.
 */

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class SupSingleFacePrice extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sup_single_face_price';

    protected $primaryKey = 'id_sf_price';

    protected $fillable = ['supplier_id', 'substance_name', 'flute_type', 'index_per_m2', 'price_basis', 'effective_date', 'activate', 'created_by', 'updated_by'];

    protected $casts = [
        'index_per_m2' => 'decimal:4',
        'effective_date' => 'date',
        'activate' => 'boolean',
    ];
}
