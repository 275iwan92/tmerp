<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinPettyCashTx extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_petty_cash_tx';

    protected $primaryKey = 'id_petty_tx';

    public const TYPES = ['TOPUP' => 'Top Up', 'ADVANCE' => 'Cash Advance', 'SETTLE' => 'Settlement', 'REIMBURSE' => 'Reimburse'];

    protected $fillable = ['petty_cash_id', 'tx_no', 'tx_date', 'tx_type', 'po_id', 'po_no', 'advance_amount', 'actual_amount', 'cash_effect',
        'counter_account_id', 'voucher_id', 'receiver_name', 'attachment_path', 'notes', 'status', 'created_by', 'updated_by'];

    protected $casts = ['tx_date' => 'date'];

    public function pettyCash(): BelongsTo
    {
        return $this->belongsTo(FinPettyCash::class, 'petty_cash_id', 'id_petty_cash');
    }

    public static function nextNo(): string
    {
        $prefix = 'KK/'.now()->format('y').'/';
        $last = static::where('tx_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('tx_no')->value('tx_no');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, -5)) + 1 : 1), 5, '0', STR_PAD_LEFT);
    }
}
