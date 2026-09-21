<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinCloseTask extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_close_task';

    protected $primaryKey = 'task_id';

    protected $guarded = ['task_id'];

    protected $casts = ['completed_at' => 'datetime'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinPeriod::class, 'period_id', 'period_id');
    }
}
