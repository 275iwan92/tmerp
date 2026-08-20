<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgDivision extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'org_division';

    protected $primaryKey = 'division_id';

    protected $fillable = [
        'company_id',
        'division_code',
        'division_name',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(OrgCompany::class, 'company_id', 'company_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(OrgDepartment::class, 'division_id', 'division_id');
    }
}
