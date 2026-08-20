<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinVoucherLine extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_voucher_line';

    protected $primaryKey = 'line_id';

    public $timestamps = false;

    protected $fillable = [
        'voucher_id',
        'account_id',
        'partner_type',
        'partner_id',
        'cc_id',
        'co_id',
        'debit',
        'credit',
        'narration',
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FinVoucher::class, 'voucher_id', 'voucher_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'account_id', 'account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(FinCostCenter::class, 'cc_id', 'cc_id');
    }
}
