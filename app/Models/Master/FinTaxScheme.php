<?php

/*
 * REMARK
 * Tujuan       : Master skema pajak (PPN masukan/keluaran, PPh 21/22/23/badan): akun, tanggal jatuh tempo bayar dan lapor, tarif PPh.
 * Sumber hulu  : tm2mst.fin_tax_scheme; tarif PPN efektif dari TaxRate::ppn(tanggal) (layar Tax Rate), bukan kolom rate.
 * Input        : Kode pajak, tanggal dokumen.
 * Output       : effectiveRate(tanggal) dalam desimal.
 * Dipakai oleh : ArApService, TaxEngine, TaxDesk.
 * Status       : Aktif; kolom rate dan dpp_factor baris PPN hanya keterangan hukum, tidak dipakai menghitung.
 */

namespace App\Models\Master;

use App\Support\Finance\TaxRate;
use Illuminate\Database\Eloquent\Model;

// Tarif & jatuh tempo pajak -- dibaca App\Services\Acc\TaxEngine saat hitung,
// bukan di-hardcode (financeaccounting_rules.yaml invarian tax_rate).
class FinTaxScheme extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'fin_tax_scheme';

    protected $primaryKey = 'tax_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'rate' => 'decimal:4',
        'dpp_factor' => 'decimal:6',
        'activate' => 'boolean',
    ];

    // Tarif efektif dalam desimal pada tanggal dokumen. PPN: dari Master Data > Tax Rate (tarif efektif, mis. 0,11); PPh: rate x dpp_factor skema ini.
    public function effectiveRate(?string $on = null): float
    {
        if ($this->tax_kind === 'PPN') {
            return round(TaxRate::ppn($on) / 100, 6);
        }

        return round((float) $this->rate * (float) $this->dpp_factor / 100, 6);
    }
}
