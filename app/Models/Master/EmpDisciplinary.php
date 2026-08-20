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
    ];

    protected $casts = [
        'incident_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
