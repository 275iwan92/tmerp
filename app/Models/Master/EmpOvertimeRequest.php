<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpOvertimeRequest extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_overtime_request';

    protected $primaryKey = 'id_overtime';

    protected $fillable = [
        'employee_id',
        'overtime_date',
        'ot_type',
        'duration_minutes',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'overtime_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }
}
