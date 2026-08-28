<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlowConfig extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'flow_config';

    protected $primaryKey = 'flow_config_id';

    protected $fillable = ['module_code', 'division_id', 'total_step', 'notes', 'activate', 'created_by', 'updated_by'];

    public function steps(): HasMany
    {
        return $this->hasMany(FlowConfigStep::class, 'flow_config_id', 'flow_config_id')->orderBy('step_no');
    }
}
