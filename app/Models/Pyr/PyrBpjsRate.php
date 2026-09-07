<?php

namespace App\Models\Pyr;

use Illuminate\Database\Eloquent\Model;

class PyrBpjsRate extends Model
{
    protected $connection = 'tm2pyr';

    protected $table = 'pyr_bpjs_rate';

    protected $primaryKey = 'id_rate';

    public $timestamps = false;

    protected $fillable = [
        'component',
        'rate',
        'cap_amount',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'cap_amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];
}
