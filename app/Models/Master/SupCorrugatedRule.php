<?php

/*
 * REMARK
 * Tujuan       : Model aturan lembar gelombang per supplier (lebar trim maksimum, diskon per tingkat running meter), berversi menurut tanggal berlaku.
 * Sumber hulu  : Layar Master Data > Supplier Corrugated Rule (SupplierCorrugatedRuleLookup); Excel Jo sheet UJK, RCA.
 * Input        : Baris tm2mst.sup_corrugated_rule.
 * Output       : Konstanta harga beli per supplier menurut tanggal berlaku (pola sama dengan FinExchangeRate).
 * Dipakai oleh : CorrugatedSheetPriceEngine, layar lookup terkait.
 * Status       : Aktif.
 */

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class SupCorrugatedRule extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sup_corrugated_rule';

    protected $primaryKey = 'id_corr_rule';

    protected $fillable = ['supplier_id', 'max_trim_width_cm', 'disc_300', 'disc_500', 'disc_1000', 'disc_2000', 'disc_5000', 'effective_date', 'activate', 'created_by', 'updated_by'];

    protected $casts = [
        'max_trim_width_cm' => 'decimal:2',
        'disc_300' => 'decimal:4',
        'disc_500' => 'decimal:4',
        'disc_1000' => 'decimal:4',
        'disc_2000' => 'decimal:4',
        'disc_5000' => 'decimal:4',
        'effective_date' => 'date',
        'activate' => 'boolean',
    ];
}
