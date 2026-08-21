<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MWhLocation extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'm_wh_location';

    protected $primaryKey = 'id_wh_location';

    protected $fillable = [
        'warehouse_id', 'row_code', 'col_code', 'stack_code', 'bin_code', 'capacity_kg', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity_kg' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(MWarehouse::class, 'warehouse_id', 'id_warehouse');
    }
}
