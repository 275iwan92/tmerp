<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoCountry extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'geo_country';

    protected $primaryKey = 'id_country';

    protected $fillable = [
        'country_code',
        'iso_code',
        'country_name',
        'capital_name',
        'call_country_no',
        'land_area_km2',
        'currency_id',
        'tax_percentage',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function regions(): HasMany
    {
        return $this->hasMany(GeoRegion::class, 'country_id', 'id_country');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(FinCurrency::class, 'currency_id', 'id_currency');
    }
}
