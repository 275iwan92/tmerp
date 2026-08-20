<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class FlowLog extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2mst';

    protected $table = 'flow_log';

    protected $primaryKey = 'flow_log_id';

    protected $fillable = [
        'module_code', 'doc_id', 'flow_config_id', 'flow_step_id', 'status_from', 'status_to',
        'actor_position_id', 'actual_user_id', 'is_acting', 'action', 'amount_snapshot', 'notes',
        'acted_at', 'created_by',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];
}
