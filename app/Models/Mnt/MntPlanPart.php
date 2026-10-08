<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

class MntPlanPart extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_plan_part';

    protected $primaryKey = 'id_plan_part';

    protected $guarded = ['id_plan_part'];

    protected $casts = ['qty' => 'float'];
}
