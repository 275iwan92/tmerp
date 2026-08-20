<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpShiftRoster extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_shift_roster';

    protected $primaryKey = 'roster_id';

    protected $fillable = [
        'batch_id',
        'employee_id',
        'division_id',
        'machine_id',
        'shift_id',
        'roster_date',
        'assigned_by_role',
        'is_overtime',
        'overtime_type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'roster_date' => 'date',
        'is_overtime' => 'boolean',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(EmpShiftRosterBatch::class, 'batch_id', 'batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(OrgDivision::class, 'division_id', 'division_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmpShift::class, 'shift_id', 'shift_id');
    }
}
