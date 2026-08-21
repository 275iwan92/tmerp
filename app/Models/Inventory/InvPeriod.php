<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class InvPeriod extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_period';

    protected $primaryKey = 'period_id';

    protected $fillable = [
        'i_period_code', 'i_date_start', 'i_date_end', 'i_status',
        'c_closed_by', 'c_closed_at', 'i_notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'i_date_start' => 'date',
        'i_date_end' => 'date',
        'c_closed_at' => 'datetime',
    ];
}
