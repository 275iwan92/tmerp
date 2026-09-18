<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpCostingPriceTier extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_costing_price_tier';

    protected $primaryKey = 'id_price_tier';

    protected $fillable = [
        'costing_id',
        'tier_id',
        'qty_pcs',
        'is_primary',
        'tolerance_pct',
        'margin_pct',
        'margin_ppn_pct',
        'rounding_mode',
        'rounding_step',
        'c_tooling_pc',
        'c_hpp_pc',
        'c_ppn_input_credit_pc',
        'c_after_tolerance_pc',
        'c_after_round_pc',
        'c_after_margin_pc',
        'c_after_ppn_pc',
        'price_per_pcs',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty_pcs' => 'decimal:2',
        'is_primary' => 'boolean',
        'tolerance_pct' => 'decimal:2',
        'margin_pct' => 'decimal:2',
        'margin_ppn_pct' => 'decimal:2',
        'rounding_step' => 'decimal:2',
        'c_tooling_pc' => 'decimal:4',
        'c_hpp_pc' => 'decimal:4',
        'c_ppn_input_credit_pc' => 'decimal:4',
        'c_after_tolerance_pc' => 'decimal:4',
        'c_after_round_pc' => 'decimal:4',
        'c_after_margin_pc' => 'decimal:4',
        'c_after_ppn_pc' => 'decimal:4',
        'price_per_pcs' => 'decimal:4',
    ];

    public function costing(): BelongsTo
    {
        return $this->belongsTo(IqpCosting::class, 'costing_id', 'id_costing');
    }

    // tier_id -> iqp_tier: FK fisik sama-DB (tm2sdi) sebenarnya dimungkinkan, tapi
    // sengaja logical karena baris ini snapshot (boleh berdiri sendiri lepas dari tier asal).
}
