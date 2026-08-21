<?php

namespace App\Models\Pur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PvLine extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'pv_line';

    protected $primaryKey = 'pv_line_id';

    protected $fillable = [
        'pv_id', 'line_no', 'po_line_id', 'c_po_id', 'c_po_doc_no', 'c_po_line_no',
        'item_id', 'c_item_code', 'i_description', 'is_stock', 'i_supplier_do_no',
        'i_qty_purchase', 'i_uom_purchase', 'i_qty_stock', 'i_uom_stock', 'c_conv_used', 'is_conv_manual',
        'qc_qty_status', 'qc_quality_status', 'c_qty_accepted', 'c_qty_rejected', 'qc_note', 'qc_by', 'qc_at',
        'c_tol_pct', 'c_qty_variance_pct', 'is_tol_exceeded', 'tol_override_by', 'tol_override_at', 'tol_override_reason',
        'c_unit_price', 'c_line_value',
        'i_lot_no', 'i_location_code', 'i_co_line_id', 'c_qty_invoiced', 'c_qty_returned',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_stock' => 'boolean',
        'is_conv_manual' => 'boolean',
        'is_tol_exceeded' => 'boolean',
        'qc_at' => 'datetime',
        'tol_override_at' => 'datetime',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PvReceipt::class, 'pv_id', 'pv_id');
    }

    public function poLine(): BelongsTo
    {
        return $this->belongsTo(PoLine::class, 'po_line_id', 'po_line_id');
    }

    // PROC_TIER_2 §A-4.3 -- variance thd SISA po_line (bukan qty PO penuh), krn
    // penerimaan bisa bertahap.
    public function recalcVariance(float $qtyOutstandingBeforeThis): void
    {
        if ($qtyOutstandingBeforeThis <= 0) {
            $this->c_qty_variance_pct = 0;
            $this->is_tol_exceeded = false;

            return;
        }

        $variance = ($this->i_qty_purchase - $qtyOutstandingBeforeThis) / $qtyOutstandingBeforeThis * 100;
        $this->c_qty_variance_pct = round($variance, 4);
        $this->is_tol_exceeded = $variance > (float) ($this->c_tol_pct ?? 5);
    }
}
