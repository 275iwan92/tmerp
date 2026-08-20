<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpBpjsKesehatan extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_bpjs_kesehatan';

    protected $primaryKey = 'employee_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'no_kepesertaan',
        'kelas_rawat',
        'is_active',
        'dependents_count',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'dependents_count' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
