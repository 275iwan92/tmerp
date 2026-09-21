<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;

class FinPeriodLog extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_period_log';

    protected $primaryKey = 'log_id';

    protected $guarded = ['log_id'];

    public $timestamps = false;

    protected $casts = ['changed_at' => 'datetime'];
}
