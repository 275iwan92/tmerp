<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpBankAccount extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_bank_account';

    protected $primaryKey = 'bank_account_id';

    protected $fillable = [
        'employee_id',
        'bank_name',
        'account_no',
        'account_holder_name',
        'is_primary',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'activate' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
