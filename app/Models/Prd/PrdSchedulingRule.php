<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

// Constraint Rule Library (Batching/Sequencing) sebagai DATA -- Tier1 §5A.7.
// Dibaca runtime oleh SchedulingRuleEngine (app/Services/Scheduling).
class PrdSchedulingRule extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_scheduling_rule';

    protected $primaryKey = 'rule_id';

    public $timestamps = false;

    protected $fillable = [
        'rule_type', 'rule_name', 'applies_to_machine_id', 'applies_to_process',
        'condition_field', 'condition_operator', 'rule_status',
        'effective_from', 'effective_to', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'approved_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('rule_status', 'ACTIVE')
            ->where(function ($q) {
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', now());
            });
    }

    public function scopeForResource($query, ?int $machineId, ?string $process)
    {
        return $query->where(function ($q) use ($machineId) {
            $q->whereNull('applies_to_machine_id')->orWhere('applies_to_machine_id', $machineId);
        })->where(function ($q) use ($process) {
            $q->whereNull('applies_to_process')->orWhere('applies_to_process', $process);
        });
    }

    // Tier1 §9.7 -- hanya boleh ACTIVE lewat approval role minimal Manager
    // Produksi/Manager Operasional. Guard role ditegakkan di Policy/Gate
    // pemanggil, method ini murni transisi status setelah otorisasi lolos.
    public function activate(string $approvedBy): void
    {
        $this->rule_status = 'ACTIVE';
        $this->approved_by = $approvedBy;
        $this->approved_at = now();
        $this->save();
    }
}
