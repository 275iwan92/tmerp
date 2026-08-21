<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MWarehouse extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'm_warehouse';

    protected $primaryKey = 'id_warehouse';

    protected $fillable = [
        'warehouse_code', 'warehouse_name', 'warehouse_type', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(MWhLocation::class, 'warehouse_id', 'id_warehouse')->orderBy('row_code')->orderBy('col_code')->orderBy('stack_code');
    }
}
