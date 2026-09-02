<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class MatFlapRule extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_flap_rule';

    protected $primaryKey = 'id_flap_rule';

    protected $fillable = [
        'material_type',
        'grammage_range',
        'glue_type',
        'flap_min_mm',
        'flap_max_mm',
        'activate',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];
}
