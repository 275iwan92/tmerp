<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Baris Customer Invoice = satu baris Surat Jalan (tm2sdi.do_line) yang ditagih. Harga dan nama disalin saat dibuat.
class FinInvoiceLine extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_invoice_line';

    protected $primaryKey = 'id_invoice_line';

    protected $fillable = ['invoice_id', 'line_no', 'do_line_id', 'delivery_id', 'delivery_no', 'delivery_date', 'co_order_item_id', 'product_id', 'description',
        'color_config', 'order_type', 'is_lump_sum', 'qty_delivered', 'qty_received', 'qty_invoiced', 'qty_unit', 'unit_price', 'line_total', 'created_by', 'updated_by'];

    protected $casts = ['delivery_date' => 'date', 'is_lump_sum' => 'boolean', 'qty_delivered' => 'decimal:2', 'qty_received' => 'decimal:2', 'qty_invoiced' => 'decimal:2',
        'unit_price' => 'decimal:4', 'line_total' => 'decimal:2'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinInvoice::class, 'invoice_id', 'invoice_id');
    }
}
