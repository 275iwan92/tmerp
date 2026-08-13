<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class UomUnit extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'uom_unit';

    protected $primaryKey = 'id_uom';

    protected $fillable = [
        'uom_code',
        'uom_name',
        'dimension',
        'is_base',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_base' => 'boolean',
        'is_active' => 'boolean',
    ];
}
