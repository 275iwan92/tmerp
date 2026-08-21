<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MWhLocation extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'm_wh_loc';

    protected $primaryKey = 'location_id';

    protected $fillable = [
        'warehouse_id', 'row_code', 'col_code', 'stack_code', 'location_code', 'location_name', 'location_type',
        'is_usable', 'capacity_kg', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_usable' => 'boolean',
        'capacity_kg' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(MWarehouse::class, 'warehouse_id', 'id_warehouse');
    }
}
