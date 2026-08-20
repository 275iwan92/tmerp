<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpLeaveBalance extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_leave_balance';

    protected $primaryKey = 'balance_id';

    protected $fillable = [
        'employee_id',
        'year',
        'entitlement_days',
        'used_days',
        'carried_over_days',
    ];

    protected $casts = [
        'year' => 'integer',
        'entitlement_days' => 'integer',
        'used_days' => 'integer',
        'carried_over_days' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }

    public function remainingDays(): int
    {
        return $this->entitlement_days + $this->carried_over_days - $this->used_days;
    }
}
