<?php

namespace App\Models\Pur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PqQuotation extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'pq_quotation';

    protected $primaryKey = 'pq_id';

    protected $fillable = [
        'doc_no', 'i_doc_date', 'i_supplier_id', 'i_supplier_text', 'i_pr_id',
        'i_quotation_valid_until', 'i_currency_id', 'i_fx_rate', 'i_payment_term',
        'i_lead_time_day', 'i_notes', 'source_path', 'source_name', 'extract_status', 'extract_note',
        'c_total_idr', 'status', 'cancel_reason',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'i_doc_date' => 'date',
        'i_quotation_valid_until' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PqLine::class, 'pq_id', 'pq_id')->orderBy('line_no');
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PrRequisition::class, 'i_pr_id', 'pr_id');
    }

    public static function nextDocNo(): string
    {
        $prefix = 'PQ'.now()->format('y');
        $last = static::where('doc_no', 'like', "{$prefix}%")->lockForUpdate()->orderByDesc('doc_no')->value('doc_no');
        $seq = $last ? ((int) substr($last, 4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function recalcTotal(): void
    {
        $this->c_total_idr = $this->lines()->sum('c_subtotal_idr');
    }
}
