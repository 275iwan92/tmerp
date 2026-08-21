<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgDepartment extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'org_department';

    protected $primaryKey = 'department_id';

    protected $fillable = [
        'legacy_department_id',
        'division_id',
        'company_id',
        'department_code',
        'department_name',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(OrgDivision::class, 'division_id', 'division_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(OrgCompany::class, 'company_id', 'company_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(OrgPosition::class, 'department_id', 'department_id');
    }
}
