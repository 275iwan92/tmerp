<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class FinPaymentTerm extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'fin_payment_term';

    protected $primaryKey = 'payment_term_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'payment_term_code',
        'term_label',
        'days',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'days' => 'integer',
        'is_active' => 'boolean',
    ];
}
