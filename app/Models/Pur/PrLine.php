<?php

namespace App\Models\Pur;

use App\Models\Master\MRawMaterial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrLine extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'pr_line';

    protected $primaryKey = 'pr_line_id';

    protected $fillable = [
        'pr_id', 'line_no', 'i_item_source', 'item_id', 'c_item_code', 'i_description',
        'i_qty', 'i_uom', 'i_est_price', 'c_est_subtotal',
        'i_department_id', 'i_coa_account_id', 'i_co_line_id',
        'is_stock', 'i_need_date', 'i_notes',
        'c_qty_allocated', 'c_qty_ordered', 'c_line_status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_stock' => 'boolean',
        'i_need_date' => 'date',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PrRequisition::class, 'pr_id', 'pr_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MRawMaterial::class, 'item_id', 'material_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PrLineAlloc::class, 'pr_line_id', 'pr_line_id')->orderBy('alloc_no');
    }

    public function recalcSubtotal(): void
    {
        $this->c_est_subtotal = $this->i_est_price !== null
            ? round((float) $this->i_qty * (float) $this->i_est_price, 4)
            : null;
    }
}
