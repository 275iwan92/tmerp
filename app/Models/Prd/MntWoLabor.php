<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

class MntWoLabor extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'mnt_wo_labor';

    protected $primaryKey = 'id_wo_labor';

    protected $guarded = ['id_wo_labor'];

    protected $casts = ['work_date' => 'date', 'rate_snapshot' => 'float'];
}
