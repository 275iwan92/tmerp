<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;

class FinCostCenter extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_cost_center';

    protected $primaryKey = 'cc_id';

    protected $fillable = [
        'cc_code',
        'cc_name',
        'cc_group',
        'org_dept_id',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];
}
