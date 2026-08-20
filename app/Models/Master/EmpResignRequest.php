<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpResignRequest extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_resign_request';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'effective_date',
        'reason',
        'status',
        'requested_at',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
