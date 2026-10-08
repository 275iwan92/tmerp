<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Maintenance Order: satu tabel untuk PMO (Preventive, Inspection), BMO (Breakdown, Corrective) dan IMO (Improvement).
// Awalan nomor mengikuti jenis. Siklus dokumen: Draft -> Confirmed (status kerja Scheduled, In Progress, Waiting Part, Done) -> Closed, atau Cancelled.
class MntOrder extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_order';

    protected $primaryKey = 'id_order';

    protected $guarded = ['id_order'];

    protected $casts = [
        'needs_stop' => 'boolean', 'stat_confirm' => 'boolean', 'stat_cancel' => 'boolean', 'stat_close' => 'boolean', 'planned_start' => 'datetime', 'planned_end' => 'datetime',
        'actual_start' => 'datetime', 'actual_end' => 'datetime', 'closed_at' => 'datetime', 'meter_at_done' => 'float',
    ];

    public const TYPES = ['PREVENTIVE' => 'Preventive', 'INSPECTION' => 'Inspection', 'BREAKDOWN' => 'Breakdown', 'CORRECTIVE' => 'Corrective', 'IMPROVEMENT' => 'Improvement'];

    // Jenis -> awalan nomor dokumen (PMO, BMO, IMO).
    public const PREFIX = ['PREVENTIVE' => 'PMO', 'INSPECTION' => 'PMO', 'BREAKDOWN' => 'BMO', 'CORRECTIVE' => 'BMO', 'IMPROVEMENT' => 'IMO'];

    public const PRIORITIES = ['P1' => 'P1 Emergency', 'P2' => 'P2 High', 'P3' => 'P3 Normal', 'P4' => 'P4 Low'];

    public const WORK = ['DRAFT' => 'Draft', 'SCHEDULED' => 'Scheduled', 'IN_PROGRESS' => 'In Progress', 'WAITING_PART' => 'Waiting Part', 'DONE' => 'Done'];

    public const WORK_TONES = ['Draft' => 'neutral', 'Scheduled' => 'info', 'In Progress' => 'accent', 'Waiting Part' => 'warn', 'Done' => 'success', 'Closed' => 'neutral', 'Cancelled' => 'danger'];

    public const TYPE_TONES = ['Preventive' => 'accent', 'Inspection' => 'success', 'Breakdown' => 'danger', 'Corrective' => 'warn', 'Improvement' => 'info'];

    public static function prefixOf(string $type): string
    {
        return self::PREFIX[$type] ?? 'MNO';
    }

    /** @return list<string> jenis satu pintu menu: PMO = Preventive + Inspection, BMO = Breakdown + Corrective. */
    public static function typesOf(string $series): array
    {
        return array_keys(array_filter(self::PREFIX, fn ($p) => $p === $series));
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(MntOrderTask::class, 'order_id', 'id_order')->orderBy('seq_no');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(MntOrderPart::class, 'order_id', 'id_order');
    }

    public function labor(): HasMany
    {
        return $this->hasMany(MntOrderLabor::class, 'order_id', 'id_order');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MntAsset::class, 'asset_id', 'id_asset');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MntPlan::class, 'plan_id', 'id_plan');
    }

    // DRAFT | OPEN (Confirmed, belum Close) | CLOSED | CANCELLED
    public function docStatus(): string
    {
        return match (true) {
            $this->stat_cancel => 'CANCELLED', $this->stat_close => 'CLOSED', $this->stat_confirm => 'OPEN', default => 'DRAFT',
        };
    }

    public function isWorking(): bool
    {
        return $this->docStatus() === 'OPEN' && in_array($this->work_status, ['IN_PROGRESS', 'WAITING_PART'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->docStatus()) {
            'DRAFT' => 'Draft', 'CLOSED' => 'Closed', 'CANCELLED' => 'Cancelled', default => self::WORK[$this->work_status] ?? $this->work_status,
        };
    }

    public function totalCost(): float
    {
        return (float) $this->cost_part + (float) $this->cost_labor + (float) $this->cost_vendor;
    }
}
