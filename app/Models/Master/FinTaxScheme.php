<?php

namespace App\Models\Master;

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

    // Tarif efektif dalam desimal (PPN 12% x 11/12 = 0,11).
    public function effectiveRate(): float
    {
        return round((float) $this->rate * (float) $this->dpp_factor / 100, 6);
    }
}
