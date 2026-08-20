<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmpShiftRosterBatch extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_shift_roster_batch';

    protected $primaryKey = 'batch_id';

    protected $fillable = [
        'division_id',
        'date_from',
        'date_to',
        'status',
        'confirmed_by',
        'confirmed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(OrgDivision::class, 'division_id', 'division_id');
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(EmpShiftRoster::class, 'batch_id', 'batch_id');
    }
}
