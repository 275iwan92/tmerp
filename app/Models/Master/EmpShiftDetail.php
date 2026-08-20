<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpShiftDetail extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_shift_detail';

    protected $primaryKey = 'shift_detail_id';

    protected $fillable = [
        'shift_id',
        'day_of_week',
        'start_time',
        'break_out',
        'break_in',
        'end_time',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'break_out' => 'datetime:H:i',
        'break_in' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmpShift::class, 'shift_id', 'shift_id');
    }
}
