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
        'SHIPPING' => 'Shipping', 'REJECT' => 'Reject / Waste', 'CONSIGN' => 'Customer Consignment', 'TOOLING' => 'Tooling (Die & Plate)',
        // v2.01 (user 2026-10-08): WIP lantai produksi; SUBCON / TRANSIT / LOSS = area VIRTUAL (tanpa tempat fisik, tidak tampil di peta).
        'WIP' => 'WIP (Production Floor)', 'SUBCON' => 'At Subcontractor (virtual)', 'TRANSIT' => 'In Transit (virtual)', 'LOSS' => 'Loss (virtual)'];

    // Jenis area yang virtual: stok ada di buku tetapi tidak punya tempat di denah.
    public const VIRTUAL_TYPES = ['SUBCON', 'TRANSIT', 'LOSS'];

    // Aturan area (D24): satu item per bin, campur item, atau satu batch per bin; urutan ambil FIFO atau FEFO.
    public const MIX_RULES = ['ANY' => 'Mixed items', 'ONE_ITEM' => 'One item per bin', 'ONE_BATCH' => 'One batch per bin'];

    public const PICK_ORDERS = ['FIFO' => 'FIFO (oldest received first)', 'FEFO' => 'FEFO (earliest expiry first)'];

    public const MODES = ['RACK' => 'Rack', 'FLOOR' => 'Floor (lane x slot)', 'AREA' => 'Open area (one bin)'];

    public const STOCK_CLASSES = ['ANY' => 'Any', 'RM' => 'Raw Material', 'SFG' => 'Semi Finished Goods', 'FG' => 'Finished Goods'];

    // Warna area di peta per jenis (tone DevLib, bukan hex).
    public const TONES = ['RECEIVING' => 'violet', 'QC_HOLD' => 'amber', 'STORAGE' => 'accent', 'STAGING' => 'teal', 'SHIPPING' => 'green',
        'REJECT' => 'danger', 'CONSIGN' => 'pink', 'TOOLING' => 'neutral', 'WIP' => 'violet', 'SUBCON' => 'neutral', 'TRANSIT' => 'neutral', 'LOSS' => 'danger'];

    protected $connection = 'tm2mst';

    protected $table = 'wh_zone';

    protected $primaryKey = 'id_zone';

    protected $guarded = ['id_zone'];

    protected $casts = ['activate' => 'boolean', 'floor_no' => 'integer', 'map_x' => 'float', 'map_y' => 'float', 'map_w' => 'float', 'map_h' => 'float', 'stack_height_m' => 'float', 'max_stack' => 'integer',
        'is_virtual' => 'boolean', 'count_every_days' => 'integer'];

    // is_virtual selalu mengikuti jenis area (satu sumber kebenaran; layar Storage Areas tidak mengisinya).
    protected static function booted(): void
    {
        static::saving(function (self $zone) {
            $zone->is_virtual = $zone->isVirtualType();
        });
    }

    public function isVirtualType(): bool
    {
        return in_array($this->zone_type, self::VIRTUAL_TYPES, true);
    }

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
