<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class FlowPositionActing extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'flow_position_acting';

    protected $primaryKey = 'acting_id';

    protected $fillable = ['position_id', 'acting_user_id', 'start_date', 'end_date', 'reason', 'activate', 'created_by', 'updated_by'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
