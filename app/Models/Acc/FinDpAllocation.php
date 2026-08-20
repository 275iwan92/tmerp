<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// FINANCE_DOWN_PAYMENT_TIER_1-4.md §0/§2B-3 — alokasi DP -> invoice final
// (M:N). PK tabel aktual `allocation_id` (bukan `dp_allocation_id` seperti
// draf DDL dokumen) — tabel sudah diterapkan langsung di MySQL sebelum
// migration Laravel dibuat, lihat migration 2026_08_19_000020.
class FinDpAllocation extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_dp_allocation';

    protected $primaryKey = 'allocation_id';

    protected $fillable = [
        'dp_invoice_id',
        'final_invoice_id',
        'allocated_base',
        'allocated_tax',
        'allocation_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'allocated_base' => 'decimal:4',
        'allocated_tax' => 'decimal:4',
        'allocation_date' => 'date',
    ];

    public function dpInvoice(): BelongsTo
    {
        return $this->belongsTo(FinInvoice::class, 'dp_invoice_id', 'invoice_id');
    }

    public function finalInvoice(): BelongsTo
    {
        return $this->belongsTo(FinInvoice::class, 'final_invoice_id', 'invoice_id');
    }
}
