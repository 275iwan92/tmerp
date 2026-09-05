<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpDisciplinary extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_disciplinary';

    protected $primaryKey = 'disciplinary_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'type',
        'incident_date',
        'reason',
        'related_leave_request_id',
        'sanksi_referensi',
        'issued_by',
        'effective_end',
        'working_days_target',
        'working_days_counted',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'effective_end' => 'date',
        'created_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }

    // SP1/SP2/SKR/DRM aktif pada tanggal tsb -- effective_end NULL berarti
    // masih berjalan (Tier 2 §6 / Tier 3 emp_disciplinary_status).
    public function coversDate(\DateTimeInterface $date): bool
    {
        if (! in_array($this->type, ['SP1', 'SP2', 'SP3', 'SKR', 'DRM'], true)) {
            return false;
        }

        $start = $this->incident_date;
        if ($start && $date < $start) {
            return false;
        }

        return ! $this->effective_end || $date <= $this->effective_end;
    }
}
