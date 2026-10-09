<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Baris landed cost: satu baris penerimaan (pv_line) beserta bagian biayanya.
class InvLandedCostLine extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_landed_cost_line';

    protected $primaryKey = 'id_landed_cost_line';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['qty_received' => 'float', 'base_value' => 'float', 'qty_on_hand' => 'float', 'allocated' => 'float', 'to_stock' => 'float', 'to_variance' => 'float', 'card_value' => 'float'];
}
