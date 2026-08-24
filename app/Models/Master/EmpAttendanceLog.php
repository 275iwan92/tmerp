<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpAttendanceLog extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_attendance_log';

    protected $primaryKey = 'attendance_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'clock_in_at',
        'clock_out_at',
        'source',
        'import_batch_ref',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
