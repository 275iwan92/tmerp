<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;

class FinApprovalRule extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_approval_rule';

    protected $primaryKey = 'rule_id';

    protected $guarded = ['rule_id'];

    protected $casts = ['min_amount' => 'decimal:4', 'segregation_of_duties' => 'boolean', 'activate' => 'boolean'];
}
