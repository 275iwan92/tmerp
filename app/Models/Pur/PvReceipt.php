<?php

namespace App\Models\Pur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PvReceipt extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'pv_receipt';

    protected $primaryKey = 'pv_id';

    protected $fillable = [
        'doc_no', 'i_doc_date', 'i_supplier_id', 'i_warehouse_code',
        'i_supplier_do_no', 'i_vehicle_no', 'i_notes',
        'c_po_count', 'c_po_doc_list',
        'status', 'posted_at', 'posted_by', 'rm_stock_doc_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'i_doc_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PvLine::class, 'pv_id', 'pv_id')->orderBy('line_no');
    }

    public static function nextDocNo(): string
    {
        $prefix = 'SPB'.now()->format('y');

        $last = static::where('doc_no', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->value('doc_no');

        $seq = $last ? ((int) substr($last, 5)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function refreshPoSummary(): void
    {
        $this->c_po_count = PvLine::where('pv_id', $this->pv_id)->distinct('c_po_id')->count('c_po_id');
        $this->c_po_doc_list = PvLine::where('pv_id', $this->pv_id)->select('c_po_doc_no')->distinct()->orderBy('c_po_doc_no')->pluck('c_po_doc_no')->implode(', ');
    }
}
