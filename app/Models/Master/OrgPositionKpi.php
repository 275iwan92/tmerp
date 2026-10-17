<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// KPI per jabatan (terkait job description jabatan itu): target dan bobot; arah HIGHER / LOWER menentukan cara skor dihitung.
class OrgPositionKpi extends Model
{
    public const DIRECTIONS = ['HIGHER' => 'Higher', 'LOWER' => 'Lower'];

    protected $connection = 'tm2mst';

    protected $table = 'org_position_kpi';

    protected $primaryKey = 'id_position_kpi';

    protected $fillable = ['position_id', 'kpi_name', 'unit', 'direction', 'target_value', 'weight_pct', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['target_value' => 'float', 'weight_pct' => 'float', 'activate' => 'boolean'];

    public function position(): BelongsTo
    {
        return $this->belongsTo(OrgPosition::class, 'position_id', 'position_id');
    }
}
