<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;

// Model baca-saja di atas VIEW v1_fin_dp_outstanding — sisa DP per faktur,
// FINANCE_DOWN_PAYMENT_TIER_1-4.md §0.
class FinDpOutstanding extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'v1_fin_dp_outstanding';

    protected $primaryKey = 'dp_invoice_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'dp_base' => 'decimal:4',
        'dp_tax' => 'decimal:4',
        'dp_total' => 'decimal:4',
        'used_base' => 'decimal:4',
        'used_tax' => 'decimal:4',
        'dp_remaining' => 'decimal:4',
    ];
}
