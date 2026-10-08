<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

// Decision log of planning: who (login or ENGINE) did what, with old and new value and the reason.
class PrdPlanDecision extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'tm2prd';

    protected $table = 'prd_plan_decision';

    protected $primaryKey = 'decision_id';

    protected $fillable = ['run_id', 'operation_id', 'actor', 'event', 'old_value', 'new_value', 'reason'];
}
