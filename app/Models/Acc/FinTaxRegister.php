<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;

class FinTaxRegister extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_tax_register';

    protected $primaryKey = 'tax_reg_id';

    protected $guarded = ['tax_reg_id'];

    protected $casts = ['payment_due' => 'date', 'filing_due' => 'date', 'paid_at' => 'datetime', 'filed_at' => 'datetime', 'base_amount' => 'decimal:4', 'credit_amount' => 'decimal:4', 'payable_amount' => 'decimal:4'];
}
