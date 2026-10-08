<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

class MntOrderTask extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_order_task';

    protected $primaryKey = 'id_order_task';

    protected $guarded = ['id_order_task'];

    protected $casts = ['is_required' => 'boolean', 'min_value' => 'float', 'max_value' => 'float', 'measured_value' => 'float', 'done_at' => 'datetime'];

    public const RESULTS = ['OK' => 'OK', 'NOT_OK' => 'Not OK', 'NA' => 'N/A'];
}
