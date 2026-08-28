<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgCompany extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'org_company';

    protected $primaryKey = 'company_id';

    protected $fillable = [
        'company_code',
        'company_name',
        'npwp_number',
        'address',
        'phone',
        'email',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function divisions(): HasMany
    {
        return $this->hasMany(OrgDivision::class, 'company_id', 'company_id');
    }
}
