<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class SalesPerson extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sales_person';

    protected $primaryKey = 'id_sales_person';

    protected $fillable = [
        'sales_name',
        'sales_type',
        'employee_id',
        'phone',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];
}
