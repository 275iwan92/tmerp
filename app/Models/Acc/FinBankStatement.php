<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinBankStatement extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_bank_statement';

    protected $primaryKey = 'stmt_line_id';

    protected $guarded = ['stmt_line_id'];

    protected $casts = ['txn_date' => 'date', 'value_date' => 'date', 'amount_in' => 'decimal:4', 'amount_out' => 'decimal:4', 'running_balance' => 'decimal:4'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'account_id', 'account_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FinVoucher::class, 'matched_voucher_id', 'voucher_id');
    }
}
