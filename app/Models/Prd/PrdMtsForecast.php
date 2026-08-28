<?php

namespace App\Models\Prd;

use App\Models\Master\ProdProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Forecast MTS oleh Direksi + banding actual -- Tier1 §5A.8. MTS Forecast
// Entry (input manual + hitung accuracy_pct) live 2026-08-28 -- generator WO
// replenishment otomatis (F20 lanjutan) SENGAJA belum dibangun (di luar scope
// sesi ini, lihat production_scheduling_tier2.md §6 langkah 2).
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

    // Lintas-DB (tm2prd -> tm2mst) -- query terpisah, bukan join fisik.
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }
}
