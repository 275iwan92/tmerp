<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

// Spare part milik aset. Stok dibaca dari Inventory, tidak disalin ke sini.
class MntAssetPart extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_asset_part';

    protected $primaryKey = 'id_asset_part';

    protected $guarded = ['id_asset_part'];

    protected $casts = ['activate' => 'boolean', 'is_critical' => 'boolean', 'qty_per_use' => 'float', 'min_stock_suggested' => 'float'];
}
