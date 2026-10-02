<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Dulu MWhLocation (tabel m_wh_loc) -- direname 2026-08-27 jadi wh_loc,
// prefix domain baru `wh_`. Lihat migration 2026_08_27_000011.
// = BIN: tempat stok menempel (location_id di semua tabel stok). Sejak 2026-10-02 bin punya area (zone_id), kapasitas
// pallet, posisi di denah (map_x / map_y, meter) dan alasan blokir.
class WhLoc extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'wh_loc';

    protected $primaryKey = 'location_id';

    protected $fillable = [
        'warehouse_id', 'zone_id', 'row_code', 'col_code', 'stack_code', 'location_code', 'location_name', 'location_type',
        'is_usable', 'capacity_kg', 'capacity_pallet', 'map_x', 'map_y', 'block_reason', 'activate', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'is_usable' => 'boolean',
        'capacity_kg' => 'decimal:2',
        'capacity_pallet' => 'integer',
        'map_x' => 'float',
        'map_y' => 'float',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WhWarehouse::class, 'warehouse_id', 'id_warehouse');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(WhZone::class, 'zone_id', 'id_zone');
    }
}
