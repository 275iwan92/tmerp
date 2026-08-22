<?php

namespace App\Models\Pur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrtReturn extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'prt_return';

    protected $primaryKey = 'prt_id';

    protected $fillable = [
        'doc_no', 'i_doc_date', 'i_supplier_id', 'pv_id', 'po_id',
        'i_source', 'i_disposition', 'i_reason', 'c_credit_value',
        'replacement_pv_id', 'rm_stock_doc_id',
        'qc_inspector', 'comm_method', 'supplier_pic', 'physical_return_date', 'expedition',
        'cn_supplier_no', 'cn_received_date', 'replacement_due_date',
        'status', 'cancel_reason', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'i_doc_date' => 'date',
        'physical_return_date' => 'date',
        'cn_received_date' => 'date',
        'replacement_due_date' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PrtLine::class, 'prt_id', 'prt_id')->orderBy('line_no');
    }

    public function sourcePv(): BelongsTo
    {
        return $this->belongsTo(PvReceipt::class, 'pv_id', 'pv_id');
    }

    public function sourcePo(): BelongsTo
    {
        return $this->belongsTo(PoOrder::class, 'po_id', 'po_id');
    }

    public static function nextDocNo(): string
    {
        $prefix = 'NRB'.now()->format('y');
        $last = static::where('doc_no', 'like', "{$prefix}%")->lockForUpdate()->orderByDesc('doc_no')->value('doc_no');
        $seq = $last ? ((int) substr($last, 5)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function recalcCreditValue(): void
    {
        if ($this->i_disposition === 'CREDIT_NOTE') {
            $this->c_credit_value = $this->lines()->sum('c_line_value');
        }
    }
}
