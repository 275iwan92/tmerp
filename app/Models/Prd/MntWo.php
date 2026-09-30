<?php

namespace App\Models\Prd;

use App\Models\Master\ProdMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Maintenance Work Order. Siklus dokumen = stat_confirm/stat_cancel/stat_close (Draft/Confirmed/Cancelled/Closed);
// status kerja di dalam Confirmed = work_status SCHEDULED -> IN_PROGRESS <-> WAITING_PART -> DONE.
class MntWo extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'mnt_wo';

    protected $primaryKey = 'id_wo';

    protected $guarded = ['id_wo'];

    protected $casts = [
        'needs_machine_stop' => 'boolean', 'stat_confirm' => 'boolean', 'stat_cancel' => 'boolean', 'stat_close' => 'boolean',
        'planned_start' => 'datetime', 'planned_end' => 'datetime', 'actual_start' => 'datetime', 'actual_end' => 'datetime',
        'closed_at' => 'datetime', 'cost_part' => 'float', 'cost_labor' => 'float', 'cost_vendor' => 'float', 'meter_at_done' => 'float',
    ];

    public const TYPES = ['PREVENTIVE' => 'Preventive', 'BREAKDOWN' => 'Breakdown', 'CORRECTIVE' => 'Corrective', 'INSPECTION' => 'Inspection'];

    public const PRIORITIES = ['P1' => 'P1 Emergency', 'P2' => 'P2 High (24 h)', 'P3' => 'P3 Normal (1 week)', 'P4' => 'P4 Low'];

    public const WORK = ['DRAFT' => 'Draft', 'SCHEDULED' => 'Scheduled', 'IN_PROGRESS' => 'In Progress', 'WAITING_PART' => 'Waiting Part', 'DONE' => 'Done'];

    public function tasks(): HasMany
    {
        return $this->hasMany(MntWoTask::class, 'wo_id', 'id_wo')->orderBy('seq_no');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(MntWoPart::class, 'wo_id', 'id_wo');
    }

    public function labor(): HasMany
    {
        return $this->hasMany(MntWoLabor::class, 'wo_id', 'id_wo');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MntPlan::class, 'plan_id', 'id_plan');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MntRequest::class, 'request_id', 'id_request');
    }

    // Status dokumen untuk grid / form: DRAFT | OPEN (Confirmed, belum Close) | CLOSED | CANCELLED.
    public function docStatus(): string
    {
        return $this->stat_cancel ? 'CANCELLED' : ($this->stat_close ? 'CLOSED' : ($this->stat_confirm ? 'OPEN' : 'DRAFT'));
    }

    public function isWorking(): bool
    {
        return $this->docStatus() === 'OPEN' && in_array($this->work_status, ['IN_PROGRESS', 'WAITING_PART'], true);
    }

    public function totalCost(): float
    {
        return (float) $this->cost_part + (float) $this->cost_labor + (float) $this->cost_vendor;
    }
}
