<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttMobileGpsLog extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'att_mobile_gps_log';

    protected $primaryKey = 'id_gps_log';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'clock_type',
        'latitude',
        'longitude',
        'distance_meters',
        'geofence_status',
        'hr_approval_status',
        'hr_approved_by',
        'hr_approved_at',
        'hr_approval_note',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'hr_approved_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
