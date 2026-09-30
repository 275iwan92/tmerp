<?php

namespace App\Models\Prd;

use App\Models\Master\ProdMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Rencana perawatan (template WO) per mesin: pemicu waktu dan/atau meter, checklist, spare part standar.
class MntPlan extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'mnt_plan';

    protected $primaryKey = 'id_plan';

    protected $guarded = ['id_plan'];

    protected $casts = [
        'activate' => 'boolean', 'needs_machine_stop' => 'boolean', 'last_done_date' => 'date', 'next_due_date' => 'date',
        'certificate_valid_until' => 'date', 'last_done_meter' => 'float', 'next_due_meter' => 'float', 'meter_interval' => 'float',
    ];

    public const TRIGGERS = ['TIME' => 'Time', 'METER' => 'Meter', 'TIME_OR_METER' => 'Time OR Meter (first)'];

    public const UNITS = ['DAY' => 'Day', 'WEEK' => 'Week', 'MONTH' => 'Month', 'YEAR' => 'Year'];

    public const METERS = ['RUN_HOURS' => 'Run hours', 'IMPRESSIONS' => 'Impressions'];

    public const MODES = ['FLOATING' => 'Floating (from last done)', 'FIXED' => 'Fixed (calendar)'];

    public const TYPES = ['PREVENTIVE' => 'Preventive', 'INSPECTION' => 'Inspection'];

    public function tasks(): HasMany
    {
        return $this->hasMany(MntPlanTask::class, 'plan_id', 'id_plan')->orderBy('seq_no');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(MntPlanPart::class, 'plan_id', 'id_plan');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }

    public function usesTime(): bool
    {
        return $this->trigger_mode !== 'METER' && $this->interval_value && $this->interval_unit;
    }

    public function usesMeter(): bool
    {
        return $this->trigger_mode !== 'TIME' && $this->meter_type && $this->meter_interval;
    }
}
