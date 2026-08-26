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
        'invoice_number',
        'invoice_type',
        'partner_type',
        'partner_id',
        'source_type',
        'source_id',
        'co_id',
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
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'paid_amount' => 'decimal:4',
    ];

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
}
