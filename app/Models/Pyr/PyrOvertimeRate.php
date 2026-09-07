<?php

namespace App\Models\Pyr;

use Illuminate\Database\Eloquent\Model;

class PyrOvertimeRate extends Model
{
    protected $connection = 'tm2pyr';

    protected $table = 'pyr_overtime_rate';

    protected $primaryKey = 'id_rate';

    public $timestamps = false;

    protected $fillable = [
        'overtime_type',
        'multiplier',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'multiplier' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];
}
