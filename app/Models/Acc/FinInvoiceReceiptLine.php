<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinInvoiceReceiptLine extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_invoice_receipt_line';

    protected $primaryKey = 'id_invoice_receipt_line';

    protected $guarded = ['id_invoice_receipt_line'];

    protected $casts = ['invoice_date' => 'date', 'amount' => 'decimal:4'];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(FinInvoiceReceipt::class, 'invoice_receipt_id', 'id_invoice_receipt');
    }

    // Tagihan supplier yang sudah dicatat (bila ada): nomor, tanggal, faktur pajak, dan nilainya disalin ke baris ini.
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinInvoice::class, 'invoice_id', 'invoice_id');
    }
}
