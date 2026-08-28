<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoCity extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'geo_city';

    protected $primaryKey = 'id_city';

    protected $fillable = [
        'region_id',
        'city_code',
        'city_type',
        'city_name',
        'post_code',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(GeoRegion::class, 'region_id', 'id_region');
    }
}
