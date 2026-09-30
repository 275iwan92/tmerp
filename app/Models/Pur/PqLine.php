<?php

namespace App\Models\Pur;

use App\Models\Master\MatRaw;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PqLine extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'pq_line';

    protected $primaryKey = 'pq_line_id';

    protected $fillable = [
        'pq_id', 'line_no', 'pr_line_id', 'item_id', 'i_description', 'i_source_text', 'c_match_pct',
        'i_qty', 'i_uom', 'i_price_fx', 'c_price_idr', 'c_subtotal_idr',
        'i_lead_time_day', 'i_notes', 'created_by', 'updated_by',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(PqQuotation::class, 'pq_id', 'pq_id');
    }

    public function prLine(): BelongsTo
    {
        return $this->belongsTo(PrLine::class, 'pr_line_id', 'pr_line_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MatRaw::class, 'item_id', 'material_id');
    }

    public function recalc(float $fxRate): void
    {
        $this->c_price_idr = (float) $this->i_price_fx * $fxRate;
        $this->c_subtotal_idr = (float) $this->i_qty * $this->c_price_idr;
    }
}
