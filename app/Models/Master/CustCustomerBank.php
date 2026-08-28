<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustCustomerBank extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'cust_customer_bank';

    protected $primaryKey = 'id_customer_bank';

    protected $fillable = [
        'customer_id',
        'bank_code',
        'bank_account_no',
        'bank_account_name',
        'activate',
        'legacy_vendor_bank_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }
}
