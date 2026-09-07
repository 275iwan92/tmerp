<?php

namespace App\Models\Pyr;

use Illuminate\Database\Eloquent\Model;

class PyrPph21TerBracket extends Model
{
    protected $connection = 'tm2pyr';

    protected $table = 'pyr_pph21_ter_bracket';

    protected $primaryKey = 'id_bracket';

    public $timestamps = false;

    protected $fillable = [
        'category',
        'bruto_upper_bound',
        'rate',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'bruto_upper_bound' => 'decimal:2',
        'rate' => 'decimal:6',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];
}
