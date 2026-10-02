<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Area penyimpanan di satu lantai gedung (docs/warehouselayout_tier1.md): persegi dalam meter di denah lantai itu.
// Bin (wh_loc) menempel ke area lewat wh_loc.zone_id.
class WhZone extends Model
{
    public const TYPES = ['RECEIVING' => 'Receiving', 'QC_HOLD' => 'QC Hold', 'STORAGE' => 'Storage', 'STAGING' => 'Production Staging',
        'SHIPPING' => 'Shipping', 'REJECT' => 'Reject / Waste', 'CONSIGN' => 'Customer Consignment', 'TOOLING' => 'Tooling (Die & Plate)'];

    public const MODES = ['RACK' => 'Rack', 'FLOOR' => 'Floor (lane x slot)', 'AREA' => 'Open area (one bin)'];

    public const STOCK_CLASSES = ['ANY' => 'Any', 'RM' => 'Raw Material', 'SFG' => 'Semi Finished Goods', 'FG' => 'Finished Goods'];

    // Warna area di peta per jenis (tone DevLib, bukan hex).
    public const TONES = ['RECEIVING' => 'violet', 'QC_HOLD' => 'amber', 'STORAGE' => 'accent', 'STAGING' => 'teal', 'SHIPPING' => 'green',
        'REJECT' => 'danger', 'CONSIGN' => 'pink', 'TOOLING' => 'neutral'];

    protected $connection = 'tm2mst';

    protected $table = 'wh_zone';

    protected $primaryKey = 'id_zone';

    protected $guarded = ['id_zone'];

    protected $casts = ['activate' => 'boolean', 'floor_no' => 'integer', 'map_x' => 'float', 'map_y' => 'float', 'map_w' => 'float', 'map_h' => 'float', 'stack_height_m' => 'float', 'max_stack' => 'integer'];

    public function areaM2(): float
    {
        return round((float) $this->map_w * (float) $this->map_h, 2);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WhWarehouse::class, 'warehouse_id', 'id_warehouse');
    }

    public function bins(): HasMany
    {
        return $this->hasMany(WhLoc::class, 'zone_id', 'id_zone')->orderBy('row_code')->orderBy('col_code')->orderBy('stack_code');
    }
}
