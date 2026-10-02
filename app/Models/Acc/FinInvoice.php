<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinInvoice extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_invoice';

    protected $primaryKey = 'invoice_id';

    protected $fillable = [
        'data_origin',
        'invoice_number',
        'invoice_type',
        'partner_type',
        'partner_id',
        'source_type',
        'source_id',
        'co_id',
        'description',
        'partner_ref',
        'tax_invoice_no',
        'tax_validated',
        'pph_amount',
        'pph_code',
        'revenue_account_id',
        'doc_status',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'voucher_id',
        'payment_status',
        'created_by',
        'updated_by',
        // Customer Invoice berbaris (2026-10-02, customerinvoice_accounting.md)
        'is_ppn', 'tax_rate', 'payment_term_code', 'customer_po_no', 'bill_to_name', 'bill_to_address', 'company_bank_id',
        'discount_pct', 'discount_amount', 'charge_amount', 'charge_note', 'dp_applied_amount', 'dp_plan', 'order_amount', 'dp_percent',
        'notes', 'confirmed_by', 'confirmed_at', 'cancel_reason', 'cancelled_by', 'cancelled_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'pph_amount' => 'decimal:4',
        'tax_validated' => 'boolean',
        'is_ppn' => 'boolean',
        'dp_plan' => 'array',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(FinInvoiceLine::class, 'invoice_id', 'invoice_id')->orderBy('line_no');
    }

    public function companyBank(): BelongsTo
    {
        return $this->belongsTo(FinCompanyBank::class, 'company_bank_id', 'id_company_bank');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FinVoucher::class, 'voucher_id', 'voucher_id');
    }

    // DP -> invoice final mana saja dia sudah dipotong (downpayment_tier1-4.md §0).
    public function dpAllocations(): HasMany
    {
        return $this->hasMany(FinDpAllocation::class, 'dp_invoice_id', 'invoice_id');
    }

    // Invoice final -> DP mana saja yang memotongnya.
    public function appliedDpAllocations(): HasMany
    {
        return $this->hasMany(FinDpAllocation::class, 'final_invoice_id', 'invoice_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FinPaymentAlloc::class, 'invoice_id', 'invoice_id');
    }

    // Sisa tagihan = total - PPh dipotong - yang sudah dibayar/dialokasikan.
    public function outstanding(): float
    {
        return round((float) $this->total_amount - (float) $this->pph_amount - (float) $this->paid_amount, 2);
    }
}
