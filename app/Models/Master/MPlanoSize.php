<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class MPlanoSize extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'm_plano_size';

    protected $primaryKey = 'id_plano_size';

    protected $fillable = [
        'plano_name',
        'width_mm',
        'height_mm',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'width_mm' => 'decimal:2',
        'height_mm' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
