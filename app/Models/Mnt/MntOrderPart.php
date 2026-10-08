<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

class MntOrderPart extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_order_part';

    protected $primaryKey = 'id_order_part';

    protected $guarded = ['id_order_part'];

    protected $casts = ['qty_plan' => 'float', 'qty_used' => 'float', 'qty_issued' => 'float', 'unit_cost' => 'float'];
}
