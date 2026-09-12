<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpRosterScheduleDepartment extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_roster_schedule_department';

    public $timestamps = false;

    protected $fillable = [
        'schedule_id',
        'department_id',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(EmpRosterSchedule::class, 'schedule_id', 'schedule_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'department_id', 'department_id');
    }
}
