<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinPettyCash extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_petty_cash';

    protected $primaryKey = 'id_petty_cash';

    protected $fillable = ['petty_code', 'petty_name', 'custodian_user_id', 'account_id', 'imprest_amount', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinPettyCashTx::class, 'petty_cash_id', 'id_petty_cash');
    }

    public function balance(): float
    {
        return (float) $this->transactions()->where('status', 'POSTED')->sum('cash_effect');
    }
}
