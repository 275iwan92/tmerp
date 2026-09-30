<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

class MntWoPart extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'mnt_wo_part';

    protected $primaryKey = 'id_wo_part';

    protected $guarded = ['id_wo_part'];

    protected $casts = ['qty_plan' => 'float', 'qty_used' => 'float', 'unit_cost' => 'float'];
}
