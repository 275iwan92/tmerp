<?php

namespace App\Models\Pur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoOrder extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'po_order';

    protected $primaryKey = 'po_id';

    protected $fillable = [
        'doc_no', 'i_doc_date', 'i_supplier_id', 'i_pq_id',
        'i_po_type', 'i_procure_type',
        'i_currency_id', 'i_fx_rate', 'i_quotation_valid_until',
        'i_payment_term', 'i_delivery_date', 'i_ship_to', 'i_notes',
        'c_dpp', 'c_ppn', 'c_pph', 'c_grand_total', 'c_net_payable',
        'status', 'sent_at', 'is_auto_generated', 'source_note',
        'revision_no', 'parent_po_id', 'stat_cancel', 'cancel_reason',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'i_doc_date' => 'date',
        'i_delivery_date' => 'date',
        'i_quotation_valid_until' => 'date',
        'sent_at' => 'datetime',
        'is_auto_generated' => 'boolean',
        'stat_cancel' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PoLine::class, 'po_id', 'po_id')->orderBy('line_no');
    }

    public static function nextDocNo(): string
    {
        $prefix = 'PO'.now()->format('y');

        $last = static::where('doc_no', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->value('doc_no');

        $seq = $last ? ((int) substr($last, 4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    // PROC_TIER_3 §4/A-3.3 -- per baris, satu PO boleh campur barang+jasa.
    public function recalcTax(): void
    {
        $lines = $this->lines;

        $dpp = $lines->sum('c_line_net');
        $ppnRate = (float) (\Illuminate\Support\Facades\DB::connection('tm2mst')->table('sys_constant')->where('constant_key', 'PPN')->value('constant_number') ?? 11);
        $pphRate = (float) (\Illuminate\Support\Facades\DB::connection('tm2mst')->table('sys_constant')->where('constant_key', 'PPH23_JASA')->value('constant_number') ?? 2);

        $ppn = round($dpp * $ppnRate / 100, 4);
        $pph = round($lines->where('i_line_class', 'SERVICE')->sum('c_line_net') * $pphRate / 100, 4);
        $grandTotal = $dpp + $ppn;
        $netPayable = $grandTotal - $pph;

        $this->c_dpp = $dpp;
        $this->c_ppn = $ppn;
        $this->c_pph = $pph;
        $this->c_grand_total = $grandTotal;
        $this->c_net_payable = $netPayable;
    }
}
