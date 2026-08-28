<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Dulu MWhLocation (tabel m_wh_loc) -- direname 2026-08-27 jadi wh_loc,
// prefix domain baru `wh_`. Lihat migration 2026_08_27_000011.
class WhLoc extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'wh_loc';

    protected $primaryKey = 'location_id';

    protected $fillable = [
        'warehouse_id', 'row_code', 'col_code', 'stack_code', 'location_code', 'location_name', 'location_type',
        'is_usable', 'capacity_kg', 'activate', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'is_usable' => 'boolean',
        'capacity_kg' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WhWarehouse::class, 'warehouse_id', 'id_warehouse');
    }
}
