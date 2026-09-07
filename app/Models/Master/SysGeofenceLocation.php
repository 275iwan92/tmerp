<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class SysGeofenceLocation extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sys_geofence_location';

    protected $primaryKey = 'id_location';

    public $timestamps = false;

    protected $fillable = [
        'location_name',
        'latitude',
        'longitude',
        'radius_meters',
        'activate',
        'created_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'activate' => 'boolean',
    ];
}
