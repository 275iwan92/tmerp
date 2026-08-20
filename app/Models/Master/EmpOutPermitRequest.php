<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpOutPermitRequest extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_out_permit_request';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'permit_date',
        'planned_out_time',
        'planned_return_time',
        'reason',
        'fingerprint_out_at',
        'fingerprint_in_at',
        'atasan_approved_by',
        'atasan_approved_at',
        'hrd_approved_by',
        'hrd_approved_at',
        'status',
        'requested_at',
    ];

    protected $casts = [
        'permit_date' => 'date',
        'fingerprint_out_at' => 'datetime',
        'fingerprint_in_at' => 'datetime',
        'atasan_approved_at' => 'datetime',
        'hrd_approved_at' => 'datetime',
        'requested_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
