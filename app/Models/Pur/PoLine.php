<?php

namespace App\Models\Pur;

use App\Models\Master\MatRaw;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoLine extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'po_line';

    protected $primaryKey = 'po_line_id';

    protected $fillable = [
        'po_id', 'line_no', 'alloc_id', 'item_id', 'c_item_code', 'i_description', 'i_line_class',
        'i_qty', 'i_uom', 'c_uom_stock', 'c_conv_to_stock',
        'i_price', 'i_disc_pct', 'i_disc_amt', 'c_line_net',
        'i_department_id', 'i_coa_account_id', 'i_co_line_id', 'is_stock',
        'c_tol_pct', 'c_qty_received', 'c_qty_outstanding', 'c_line_status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_stock' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PoOrder::class, 'po_id', 'po_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(PrLineAlloc::class, 'alloc_id', 'alloc_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MatRaw::class, 'item_id', 'material_id');
    }

    public function recalcNet(): void
    {
        $gross = (float) $this->i_qty * (float) $this->i_price;
        $disc = $this->i_disc_amt !== null
            ? (float) $this->i_disc_amt
            : $gross * (float) ($this->i_disc_pct ?? 0) / 100;

        $this->c_line_net = round($gross - $disc, 4);
    }
}
