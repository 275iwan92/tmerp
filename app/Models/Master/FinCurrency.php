<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class FinCurrency extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'fin_currency';

    protected $primaryKey = 'id_currency';

    protected $fillable = [
        'currency_code',
        'currency_name',
        'symbol',
        'counted_word',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];
}
