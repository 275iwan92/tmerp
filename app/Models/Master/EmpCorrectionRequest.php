<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpCorrectionRequest extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_correction_request';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'field_name',
        'old_value',
        'new_value',
        'reason',
        'attachment_path',
        'requires_hr_approval',
        'status',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'requires_hr_approval' => 'boolean',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
