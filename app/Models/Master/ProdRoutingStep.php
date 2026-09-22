<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdRoutingStep extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_routing_step';

    protected $primaryKey = 'step_id';

    protected $fillable = [
        'template_id',
        'step_seq',
        'process_id',
        'preferred_machine_id',
        'is_optional',
        'branch_code',
        'qty_factor',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_optional' => 'boolean',
        'step_seq' => 'integer',
        'qty_factor' => 'decimal:3',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProdRoutingTemplate::class, 'template_id', 'template_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProdProcess::class, 'process_id', 'id_process');
    }

    public function preferredMachine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'preferred_machine_id', 'machine_id');
    }
}
