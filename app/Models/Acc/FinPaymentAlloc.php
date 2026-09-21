<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinPaymentAlloc extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_payment_alloc';

    protected $primaryKey = 'alloc_id';

    protected $guarded = ['alloc_id'];

    protected $casts = ['amount' => 'decimal:4', 'alloc_date' => 'date'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinInvoice::class, 'invoice_id', 'invoice_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FinVoucher::class, 'voucher_id', 'voucher_id');
    }
}
