<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoRegion extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'geo_region';

    protected $primaryKey = 'id_region';

    protected $fillable = [
        'country_id',
        'region_code',
        'region_name',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function cities(): HasMany
    {
        return $this->hasMany(GeoCity::class, 'region_id', 'id_region');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(GeoCountry::class, 'country_id', 'id_country');
    }
}
