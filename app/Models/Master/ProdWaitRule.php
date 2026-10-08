<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Minimum wait after a process (drying, curing) before the next one; read by PlanLoader when scheduling.
class ProdWaitRule extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_wait_rule';

    protected $primaryKey = 'id_wait_rule';

    protected $fillable = ['after_process_id', 'after_process_name', 'kind', 'wait_minutes', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];
}
