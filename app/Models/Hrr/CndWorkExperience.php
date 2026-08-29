<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;

class CndWorkExperience extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'cnd_work_experience';

    protected $primaryKey = 'id_work';

    protected $fillable = [
        'id_candidate', 'company', 'location', 'business_type', 'employee_count', 'company_phone',
        'position', 'start_date', 'end_date', 'position_end', 'position_end_start_date', 'position_end_end_date',
        'subordinate_count_start', 'subordinate_count_end', 'salary_start', 'salary_end', 'benefits',
        'supervisor_name', 'supervisor_phone', 'achievement', 'description', 'org_structure_note',
        'key_challenge', 'improvement_made', 'leave_reason',
    ];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date',
        'position_end_start_date' => 'date', 'position_end_end_date' => 'date',
        'salary_start' => 'decimal:2', 'salary_end' => 'decimal:2',
    ];
}
