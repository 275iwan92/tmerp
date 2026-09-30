<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowConfigStep extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'flow_config_step';

    protected $primaryKey = 'flow_step_id';

    protected $fillable = [
        'flow_config_id', 'step_no', 'step_type', 'status_from', 'status_to', 'status_to_reject',
        'position_id', 'min_amount', 'max_amount', 'label', 'print_label', 'activate', 'created_by', 'updated_by',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(OrgPosition::class, 'position_id', 'position_id');
    }
}
