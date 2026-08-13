<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpCostingStateLog extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_costing_state_log';

    protected $primaryKey = 'id_state_log';

    public $timestamps = false;

    protected $fillable = [
        'costing_id',
        'from_state',
        'to_state',
        'changed_at',
        'changed_by',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function costing(): BelongsTo
    {
        return $this->belongsTo(IqpCosting::class, 'costing_id', 'id_costing');
    }
}
