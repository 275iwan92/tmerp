<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;

class FinBankRecon extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_bank_recon';

    protected $primaryKey = 'recon_id';

    protected $guarded = ['recon_id'];

    protected $casts = ['statement_date' => 'date', 'statement_balance' => 'decimal:4', 'book_balance' => 'decimal:4', 'difference' => 'decimal:4'];
}
