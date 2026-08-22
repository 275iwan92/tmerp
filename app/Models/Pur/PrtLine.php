<?php

namespace App\Models\Pur;

use App\Models\Master\MRawMaterial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrtLine extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'prt_line';

    protected $primaryKey = 'prt_line_id';

    protected $fillable = [
        'prt_id', 'pv_line_id', 'line_no', 'item_id', 'c_item_code', 'i_description',
        'i_qty', 'i_uom', 'c_unit_price', 'c_line_value', 'is_stock_moved', 'i_lot_no', 'i_notes',
        'replacement_pv_id', 'replacement_qty', 'replacement_status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_stock_moved' => 'boolean',
    ];

    public function return(): BelongsTo
    {
        return $this->belongsTo(PrtReturn::class, 'prt_id', 'prt_id');
    }

    public function pvLine(): BelongsTo
    {
        return $this->belongsTo(PvLine::class, 'pv_line_id', 'pv_line_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MRawMaterial::class, 'item_id', 'material_id');
    }

    public function recalc(): void
    {
        $this->c_line_value = (float) $this->i_qty * (float) $this->c_unit_price;
    }
}
