<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttDailyStatus extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'att_daily_status';

    protected $primaryKey = 'id_daily';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'status_code',
        'finger_in',
        'finger_out',
        'shift_start',
        'shift_end',
        'minutes_late',
        'minutes_early_leave',
        'minutes_out_during_shift',
        'overtime_minutes',
        'overtime_type',
        'is_uang_roti',
        'late_violation_seq_in_period',
        'source',
        'override_by',
        'override_reason',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'finger_in' => 'datetime',
        'finger_out' => 'datetime',
        'shift_start' => 'datetime:H:i',
        'shift_end' => 'datetime:H:i',
        'is_uang_roti' => 'boolean',
        'calculated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }

    // Status yang komponen gajinya dihitung PRORATA per menit hilang (Tier 2 §4.2).
    public function isTimeVariant(): bool
    {
        return in_array($this->status_code, ['T-5', 'T+5', 'T+15', 'PA', 'PAT-5', 'PAT+5', 'KJK'], true);
    }
}
