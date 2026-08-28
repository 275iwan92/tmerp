<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrdBreakdownEvent extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_breakdown_event';

    protected $primaryKey = 'event_id';

    public $timestamps = false;

    protected $fillable = [
        'operation_id', 'machine_id', 'reported_by', 'reported_at',
        'downtime_estimate_minutes', 'reroute_decision', 'alt_machine_id',
        'subcontract_vendor_id', 'subcontract_po_id', 'subcontract_due_out', 'subcontract_due_return',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'subcontract_due_out' => 'datetime',
        'subcontract_due_return' => 'datetime',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(PrdWoOperation::class, 'operation_id', 'operation_id');
    }
}
