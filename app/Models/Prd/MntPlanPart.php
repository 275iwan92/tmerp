<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

class MntPlanPart extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'mnt_plan_part';

    protected $primaryKey = 'id_plan_part';

    protected $guarded = ['id_plan_part'];

    protected $casts = ['qty' => 'float'];
}
