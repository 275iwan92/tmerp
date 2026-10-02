<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Catatan stop mesin. Bahan mentah Availability pada OEE aktual -- tanpa log
// ini, "Target OEE" di master cuma angka hiasan yang tak pernah dibandingkan
// dengan kenyataan.
class ProdMachineDowntime extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_machine_downtime';

    protected $primaryKey = 'downtime_id';

    // is_planned default per jenis: stop terencana tidak menghukum
    // Availability, stop tak terencana menghukum. SETUP diperlakukan
    // terencana karena sudah masuk hitungan setup_minutes di master.
    public const KINDS = [
        'PLANNED_MAINT' => ['label' => 'Scheduled Maintenance', 'planned' => true],
        'SETUP' => ['label' => 'Make-ready / Setup', 'planned' => true],
        'BREAKDOWN' => ['label' => 'Machine Breakdown', 'planned' => false],
        'WAITING_MATERIAL' => ['label' => 'Waiting for Material', 'planned' => false],
        'WAITING_CREW' => ['label' => 'Waiting for Crew', 'planned' => false],
        'POWER' => ['label' => 'Power Failure', 'planned' => false],
        'OTHER' => ['label' => 'Other Cause', 'planned' => false],
    ];

    protected $fillable = [
        'machine_id',
        'downtime_kind',
        'is_planned',
        'start_at',
        'end_at',
        'duration_minutes',
        'root_cause',
        'action_taken',
        'repair_cost',
        'reported_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_planned' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'duration_minutes' => 'integer',
        'repair_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // duration_minutes disimpan supaya laporan tidak perlu TIMESTAMPDIFF
        // di setiap query; dihitung ulang tiap kali jam berubah.
        static::saving(function (self $d) {
            $d->duration_minutes = $d->start_at && $d->end_at
                ? (int) $d->start_at->diffInMinutes($d->end_at, true)
                : null;
        });
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }

    public function kindLabel(): string
    {
        return isset(self::KINDS[$this->downtime_kind]) ? t(self::KINDS[$this->downtime_kind]['label']) : $this->downtime_kind;
    }

    public function isOpen(): bool
    {
        return $this->end_at === null;
    }
}
