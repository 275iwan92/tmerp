<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpShiftSwapRequest extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_shift_swap_request';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

    protected $fillable = [
        'requester_employee_id',
        'requester_roster_id',
        'target_employee_id',
        'target_roster_id',
        'reason',
        'submitted_on_time',
        'partner_status',
        'ppic_status',
        'requested_at',
        'partner_reviewed_at',
        'ppic_reviewed_by',
        'ppic_reviewed_at',
    ];

    protected $casts = [
        'submitted_on_time' => 'boolean',
        'requested_at' => 'datetime',
        'partner_reviewed_at' => 'datetime',
        'ppic_reviewed_at' => 'datetime',
    ];

    public function requesterEmployee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'requester_employee_id', 'employee_id');
    }

    public function targetEmployee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'target_employee_id', 'employee_id');
    }

    public function requesterRoster(): BelongsTo
    {
        return $this->belongsTo(EmpShiftRoster::class, 'requester_roster_id', 'roster_id');
    }

    public function targetRoster(): BelongsTo
    {
        return $this->belongsTo(EmpShiftRoster::class, 'target_roster_id', 'roster_id');
    }
}
