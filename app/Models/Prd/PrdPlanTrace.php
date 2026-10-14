<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

/** One operation of one machine snapshot: the officer's arrangement (TRIAL while processing, FINAL when applied = training label). */
class PrdPlanTrace extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_plan_trace';

    protected $primaryKey = 'id_plan_trace';

    protected $guarded = [];

    protected $casts = ['features' => 'array', 'from_suggestion' => 'boolean'];
}
