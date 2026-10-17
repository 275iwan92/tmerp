<?php

/*
 * REMARK
 * Tujuan       : Model tarif pajak (PPN) berversi menurut tanggal berlaku (pola sama dengan FinExchangeRate).
 * Sumber hulu  : Layar Master Data > Tax Rate (TaxRateLookup).
 * Input        : Baris tm2mst.fin_tax_rate.
 * Output       : Tarif pajak yang berlaku pada suatu tanggal.
 * Dipakai oleh : TaxRate::ppn(tanggal), TaxRateLookup.
 * Status       : Aktif.
 */

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class FinTaxRate extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'fin_tax_rate';

    protected $primaryKey = 'id_tax_rate';

    protected $fillable = ['tax_code', 'rate', 'effective_date', 'activate', 'created_by', 'updated_by'];

    protected $casts = [
        'rate' => 'decimal:4',
        'effective_date' => 'date',
        'activate' => 'boolean',
    ];
}
