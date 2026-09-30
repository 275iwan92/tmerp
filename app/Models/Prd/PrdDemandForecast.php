<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

// MPS baru: forecast per customer x product x bulan. CO customer+product di bulan yang sama memakan forecast ini.
// (prd_mts_forecast = engine lama yang diarsipkan, tidak dipakai.)
class PrdDemandForecast extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_demand_forecast';

    protected $primaryKey = 'id_forecast';

    protected $guarded = ['id_forecast'];

    protected $casts = ['period_start' => 'date', 'activate' => 'boolean', 'qty_forecast' => 'float'];
}
