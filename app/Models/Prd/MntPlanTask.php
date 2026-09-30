<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

class MntPlanTask extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'mnt_plan_task';

    protected $primaryKey = 'id_plan_task';

    protected $guarded = ['id_plan_task'];

    protected $casts = ['is_required' => 'boolean', 'min_value' => 'float', 'max_value' => 'float'];

    public const KINDS = ['INSPECT' => 'Inspect', 'CLEAN' => 'Clean', 'LUBRICATE' => 'Lubricate', 'ADJUST' => 'Adjust', 'REPLACE' => 'Replace', 'MEASURE' => 'Measure'];
}
