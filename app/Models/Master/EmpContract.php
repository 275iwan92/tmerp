<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpContract extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_contract';

    protected $primaryKey = 'contract_id';

    protected $fillable = [
        'employee_id',
        'contract_no',
        'employment_type',
        'start_date',
        'end_date',
        'salary_type',
        'salary_amount',
        'is_probation',
        'probation_end_date',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'probation_end_date' => 'date',
        'salary_amount' => 'decimal:4',
        'is_probation' => 'boolean',
        'activate' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
