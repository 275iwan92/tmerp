<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustCustomerContact extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'cust_customer_contact';

    protected $primaryKey = 'id_customer_contact';

    protected $fillable = [
        'customer_id',
        'contact_name',
        'position',
        'phone',
        'email',
        'is_default',
        'is_active',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }
}
