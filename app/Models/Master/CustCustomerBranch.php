<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustCustomerBranch extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'cust_customer_branch';

    protected $primaryKey = 'id_customer_branch';

    protected $fillable = [
        'customer_id',
        'branch_name',
        'address',
        'area_id',
        'area_level',
        'postal_code',
        'phone',
        'is_default',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'activate' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }
}
