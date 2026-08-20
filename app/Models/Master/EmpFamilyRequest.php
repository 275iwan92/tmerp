<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpFamilyRequest extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_family_request';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'request_type',
        'family_id',
        'relation',
        'full_name',
        'birth_date',
        'deceased_date',
        'attachment_path',
        'status',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'deceased_date' => 'date',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(EmpFamily::class, 'family_id', 'family_id');
    }
}
