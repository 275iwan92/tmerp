<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

// One auto-plan run: a Draft until the officer confirms it. Relations to operations are logical (prd_wo_operation.draft_run_id).
class PrdPlanRun extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'tm2prd';

    protected $table = 'prd_plan_run';

    protected $primaryKey = 'run_id';

    protected $fillable = ['trigger_kind', 'status', 'plan_from', 'operations_planned', 'exceptions_found', 'duration_ms', 'summary', 'started_by', 'confirmed_at', 'confirmed_by'];

    protected $casts = ['plan_from' => 'datetime', 'confirmed_at' => 'datetime', 'summary' => 'array'];
}
