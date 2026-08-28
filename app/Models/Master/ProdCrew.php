<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdCrew extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_crew';

    protected $primaryKey = 'id_crew';

    protected $fillable = [
        'crew_name',
        'process_id',
        'employee_id',
        'legacy_employee_code',
        'notes',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProdProcess::class, 'process_id', 'id_process');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
