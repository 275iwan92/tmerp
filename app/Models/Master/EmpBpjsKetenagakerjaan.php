<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpBpjsKetenagakerjaan extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_bpjs_ketenagakerjaan';

    protected $primaryKey = 'employee_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'no_kepesertaan',
        'is_active',
        'program_jkk',
        'program_jkm',
        'program_jht',
        'program_jp',
        'program_jkp',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'program_jkk' => 'boolean',
        'program_jkm' => 'boolean',
        'program_jht' => 'boolean',
        'program_jp' => 'boolean',
        'program_jkp' => 'boolean',
        'updated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
