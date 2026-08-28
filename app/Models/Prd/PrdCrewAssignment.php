<?php

namespace App\Models\Prd;

use App\Models\Master\EmpEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Crew (Operator Utama + Co-Operator) per Task mesin -- Tier1 §5A.9.
class PrdCrewAssignment extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_crew_assignment';

    protected $primaryKey = 'assignment_id';

    public $timestamps = false;

    protected $fillable = [
        'operation_id', 'employee_id', 'crew_role', 'created_by',
    ];

    // Lintas-DB (tm2prd -> tm2mst) -- query terpisah, bukan join fisik.
    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(PrdWoOperation::class, 'operation_id', 'operation_id');
    }
}
