<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Jejak audit tiap kali Engine MRP (App\Services\Production\MrpEngine) jalan
// untuk satu WO -- production_scheduling_tier1.md §F10.
class PrdMrpRun extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2prd';

    protected $table = 'prd_mrp_run';

    protected $primaryKey = 'mrp_run_id';

    protected $fillable = [
        'wo_id', 'run_at', 'triggered_by', 'pr_id', 'status',
    ];

    protected $casts = [
        'run_at' => 'datetime',
    ];

    public function wo(): BelongsTo
    {
        return $this->belongsTo(PrdWo::class, 'wo_id', 'wo_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(PrdMrpRequirement::class, 'mrp_run_id', 'mrp_run_id');
    }
}
