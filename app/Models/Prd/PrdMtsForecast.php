<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

// Forecast MTS oleh Direksi + banding actual -- Tier1 §5A.8. Belum dipakai
// runtime di iterasi ini (MTS Forecast Entry belum dibangun).
class PrdMtsForecast extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_mts_forecast';

    protected $primaryKey = 'forecast_id';

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'period_start', 'period_end', 'forecast_qty',
        'actual_qty', 'accuracy_pct', 'input_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'forecast_qty' => 'decimal:4',
        'actual_qty' => 'decimal:4',
        'accuracy_pct' => 'decimal:4',
    ];
}
