<?php

namespace App\Models\Acc;

use App\Models\Master\SupSupplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Tanda Terima Tagihan Supplier: bukti invoice supplier sudah diterima (bukan bukti bayar, tidak membuat jurnal).
class FinInvoiceReceipt extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_invoice_receipt';

    protected $primaryKey = 'id_invoice_receipt';

    protected $guarded = ['id_invoice_receipt'];

    protected $casts = ['receipt_date' => 'date', 'planned_payment_date' => 'date', 'att_invoice' => 'boolean', 'att_tax_invoice' => 'boolean',
        'att_delivery_note' => 'boolean', 'att_payment_receipt' => 'boolean', 'stat_confirm' => 'boolean', 'stat_cancel' => 'boolean'];

    public const METHODS = ['TRANSFER' => 'Bank Transfer', 'CASH' => 'Cash', 'GIRO' => 'Giro / Cheque'];

    public const ATTACHMENTS = ['att_invoice' => 'Original invoice', 'att_tax_invoice' => 'Tax invoice', 'att_delivery_note' => 'Delivery note / goods receipt',
        'att_payment_receipt' => 'Payment receipt (kuitansi)'];

    public function lines(): HasMany
    {
        return $this->hasMany(FinInvoiceReceiptLine::class, 'invoice_receipt_id', 'id_invoice_receipt')->orderBy('line_no');
    }

    // Lintas-DB (tm2acc -> tm2mst): query terpisah lewat relasi Eloquent, bukan join.
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupSupplier::class, 'supplier_id', 'id_supplier')->withoutGlobalScopes();
    }

    public function status(): string
    {
        return $this->stat_cancel ? 'CANCELLED' : ($this->stat_confirm ? 'CONFIRMED' : 'DRAFT');
    }

    public function total(): float
    {
        return round((float) $this->lines->sum('amount'), 2);
    }

    public static function nextNo(): string
    {
        $prefix = 'TT'.now()->format('y');
        $last = static::where('receipt_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('receipt_no')->value('receipt_no');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, 4)) + 1 : 1), 5, '0', STR_PAD_LEFT);
    }
}
