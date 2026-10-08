<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

class MntOrderLabor extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_order_labor';

    protected $primaryKey = 'id_order_labor';

    protected $guarded = ['id_order_labor'];

    protected $casts = ['work_date' => 'date', 'rate_snapshot' => 'float'];
}
