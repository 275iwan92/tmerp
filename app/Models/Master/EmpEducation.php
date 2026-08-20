<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpEducation extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_education';

    protected $primaryKey = 'education_id';

    public $timestamps = true;

    const UPDATED_AT = 'updated_at';

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'employee_id',
        'education_type',
        'activity_name',
        'organizer',
        'activity_date',
        'duration_days',
        'cost',
        'funded_by',
        'certificate_path',
        'valid_until',
        'is_bonding',
        'bonding_note',
        'uploaded_by_role',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'valid_until' => 'date',
        'cost' => 'decimal:4',
        'is_bonding' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
