<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class MatCoatingRate extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_coating_rate';

    protected $primaryKey = 'id_coating_rate';

    protected $fillable = [
        'coating_type',
        'index_coating',
        'effective_date',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'index_coating' => 'decimal:4',
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];
}
