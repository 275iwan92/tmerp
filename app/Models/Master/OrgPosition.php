<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgPosition extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'org_position';

    protected $primaryKey = 'position_id';

    protected $fillable = [
        'legacy_position_id',
        'legacy_skill_rate',
        'department_id',
        'level_id',
        'position_code',
        'position_name',
        'salary_hidden_from_hr',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'salary_hidden_from_hr' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'department_id', 'department_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(OrgLevel::class, 'level_id', 'level_id');
    }
}
