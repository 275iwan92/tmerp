<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;

class FinProfitCenter extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_profit_center';

    protected $primaryKey = 'pc_id';

    protected $fillable = [
        'pc_code',
        'pc_name',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];
}
