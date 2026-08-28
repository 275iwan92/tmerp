<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpFamily extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_family';

    protected $primaryKey = 'family_id';

    protected $fillable = [
        'employee_id',
        'relation',
        'full_name',
        'birth_date',
        'nik',
        'is_deceased',
        'deceased_date',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'deceased_date' => 'date',
        'is_deceased' => 'boolean',
        'activate' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
